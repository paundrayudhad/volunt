<?php

use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\EventCustomFieldOption;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\SecurityLog;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** @return array{org: Organization, volunteer: User, event: Event, role: EventRole, field: EventCustomField} */
function daftarTesPaket(string $eventStatus = 'registration_open'): array
{
    $org = Organization::factory()->create(['status' => 'active']);
    $volunteer = User::factory()->create();
    VolunteerProfile::factory()->create(['user_id' => $volunteer->id]);
    $event = Event::factory()->create(['organization_id' => $org->id]);
    $event->forceFill(['status' => $eventStatus])->save();
    $division = EventDivision::factory()->create(['event_id' => $event->id]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $division->id,
        'quota' => 5,
        'accepted_count' => 0,
    ]);
    $field = EventCustomField::factory()->create([
        'event_id' => $event->id,
        'type' => 'text',
        'required' => false,
    ]);

    return [
        'org' => $org->refresh(),
        'volunteer' => $volunteer->refresh(),
        'event' => $event->refresh(),
        'role' => $role->refresh(),
        'field' => $field->refresh(),
    ];
}

/** @return array<string, mixed> */
function daftarTesPayload(array $setup, array $overrides = []): array
{
    return array_merge([
        'role_id' => $setup['role']->id,
        'idempotency_key' => (string) Str::uuid(),
        'answers' => [$setup['field']->id => 'Saya siap membantu.'],
    ], $overrides);
}

it('tamu diarahkan ke login saat membuka area registrasi', function (): void {
    $setup = daftarTesPaket();

    $this->get(route('registrations.index'))->assertRedirect(route('login'));
    $this->get(route('registrations.create', $setup['event']->slug))->assertRedirect(route('login'));
    $this->post(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup))
        ->assertRedirect(route('login'));
});

it('submit dengan role dan jawaban valid membuat registrasi pending', function (): void {
    $setup = daftarTesPaket();

    $res = $this->actingAs($setup['volunteer'])
        ->post(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup));

    $reg = Registration::firstOrFail();
    $res->assertRedirect(route('registrations.show', $reg->id));
    expect($reg->status)->toBe('pending')
        ->and($reg->user_id)->toBe($setup['volunteer']->id)
        ->and($reg->event_id)->toBe($setup['event']->id)
        ->and($reg->role_id)->toBe($setup['role']->id)
        ->and($reg->answers()->count())->toBe(1)
        ->and($reg->answers()->first()->value_text)->toBe('Saya siap membantu.')
        ->and($reg->histories()->count())->toBe(1)
        ->and($reg->histories()->first()->to_status)->toBe('pending');
});

it('submit tanpa profil volunteer ditolak 422', function (): void {
    $setup = daftarTesPaket();
    $userWithoutProfile = User::factory()->create();

    $this->actingAs($userWithoutProfile)
        ->postJson(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Lengkapi profil volunteer dulu.');

    expect(Registration::count())->toBe(0);
});

it('jawaban required yang kosong ditolak 422', function (): void {
    $setup = daftarTesPaket();
    $setup['field']->update(['required' => true]);

    $this->actingAs($setup['volunteer'])
        ->postJson(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup, [
            'answers' => [$setup['field']->id => ''],
        ]))
        ->assertUnprocessable();

    expect(Registration::count())->toBe(0);
});

it('jawaban field di luar event ditolak 422', function (): void {
    $setup = daftarTesPaket();
    $foreignEvent = Event::factory()->create();
    $foreignField = EventCustomField::factory()->create(['event_id' => $foreignEvent->id, 'type' => 'text']);

    $this->actingAs($setup['volunteer'])
        ->postJson(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup, [
            'answers' => [$setup['field']->id => 'Saya siap.', $foreignField->id => 'Jawaban asing.'],
        ]))
        ->assertUnprocessable();

    expect(Registration::count())->toBe(0);
});

it('double-submit dengan idempotency key sama hanya menyimpan satu record', function (): void {
    $setup = daftarTesPaket();
    $payload = daftarTesPayload($setup);

    $this->actingAs($setup['volunteer'])
        ->post(route('registrations.store', $setup['event']->slug), $payload)
        ->assertRedirect();
    $this->actingAs($setup['volunteer'])
        ->post(route('registrations.store', $setup['event']->slug), $payload)
        ->assertRedirect();

    expect(Registration::where('idempotency_key', $payload['idempotency_key'])->count())->toBe(1)
        ->and(Registration::count())->toBe(1);
});

it('withdraw milik sendiri berhasil dan milik orang lain 404', function (): void {
    $setup = daftarTesPaket();
    $this->actingAs($setup['volunteer'])
        ->post(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup))
        ->assertRedirect();
    $reg = Registration::firstOrFail();

    $this->actingAs($setup['volunteer'])
        ->post(route('registrations.withdraw', $reg->id))
        ->assertRedirect(route('registrations.show', $reg->id));

    expect($reg->refresh()->status)->toBe('withdrawn');

    $otherUser = User::factory()->create();
    VolunteerProfile::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($otherUser)
        ->post(route('registrations.withdraw', $reg->id))
        ->assertNotFound();
});

it('event yang belum membuka registrasi ditolak 422', function (): void {
    $setup = daftarTesPaket('published');

    $this->actingAs($setup['volunteer'])
        ->post(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup))
        ->assertUnprocessable();

    expect(Registration::count())->toBe(0);
});

function daftarTesPdf(string $name = 'dokumen.pdf', int $kilobytes = 100): UploadedFile
{
    $tmp = tempnam(sys_get_temp_dir(), 'pdf');
    $header = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    $padding = str_repeat('0', max(0, $kilobytes * 1024 - strlen($header)));
    file_put_contents($tmp, $header.$padding);

    return new UploadedFile($tmp, $name, 'application/pdf', null, true);
}

it('upload file valid diterima dan tersimpan', function (): void {
    Storage::fake();
    $setup = daftarTesPaket();
    $setup['field']->update(['type' => 'file']);
    $file = daftarTesPdf();

    $this->actingAs($setup['volunteer'])
        ->post(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup, [
            'answers' => [$setup['field']->id => $file],
        ]))
        ->assertRedirect();

    $reg = Registration::firstOrFail();
    $answer = $reg->answers()->firstOrFail();
    expect($answer->file_path)->not->toBeNull();
    Storage::assertExists($answer->file_path);
});

it('file dengan mime palsu ditolak dan dicatat security log', function (): void {
    Storage::fake();
    $setup = daftarTesPaket();
    $setup['field']->update(['type' => 'file']);
    $tmp = tempnam(sys_get_temp_dir(), 'txt');
    file_put_contents($tmp, 'bukan pdf asli');
    $palsu = new UploadedFile($tmp, 'dokumen.pdf', 'application/pdf', null, true);

    $this->actingAs($setup['volunteer'])
        ->postJson(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup, [
            'answers' => [$setup['field']->id => $palsu],
        ]))
        ->assertUnprocessable();

    expect(Registration::count())->toBe(0)
        ->and(SecurityLog::where('type', 'file_upload_rejected')->count())->toBe(1);
});

it('file melebihi batas ukuran ditolak 422', function (): void {
    Storage::fake();
    $setup = daftarTesPaket();
    $setup['field']->update(['type' => 'file']);
    $besar = daftarTesPdf('besar.pdf', 6000);

    $this->actingAs($setup['volunteer'])
        ->postJson(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup, [
            'answers' => [$setup['field']->id => $besar],
        ]))
        ->assertUnprocessable();

    expect(Registration::count())->toBe(0);
});

it('jawaban select dengan opsi asing ditolak 422', function (): void {
    $setup = daftarTesPaket();
    $setup['field']->update(['type' => 'select']);
    EventCustomFieldOption::unguarded(fn () => $setup['field']->options()->create([
        'label' => 'Kecil',
        'value' => 'S',
    ]));

    $this->actingAs($setup['volunteer'])
        ->postJson(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup, [
            'answers' => [$setup['field']->id => 'XXL'],
        ]))
        ->assertUnprocessable();

    expect(Registration::count())->toBe(0);
});

it('submit dengan role nonaktif ditolak 422', function (): void {
    $setup = daftarTesPaket();
    $setup['role']->forceFill(['status' => 'inactive'])->save();

    $this->actingAs($setup['volunteer'])
        ->postJson(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup))
        ->assertUnprocessable();

    expect(Registration::count())->toBe(0);
});

it('histori submit mencatat created_at', function (): void {
    $setup = daftarTesPaket();

    $this->actingAs($setup['volunteer'])
        ->post(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup))
        ->assertRedirect();

    $reg = Registration::firstOrFail();
    expect($reg->histories()->first()?->created_at)->not->toBeNull();
});

it('tombol tarik disembunyikan untuk pendaftaran accepted', function (): void {
    $setup = daftarTesPaket();
    $this->actingAs($setup['volunteer'])
        ->post(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup))
        ->assertRedirect();
    $reg = Registration::firstOrFail();
    $reg->forceFill(['status' => 'accepted'])->save();

    $this->actingAs($setup['volunteer'])
        ->get(route('registrations.show', $reg->id))
        ->assertOk()
        ->assertDontSee('Tarik pendaftaran');

    $reg->forceFill(['status' => 'under_review'])->save();

    $this->actingAs($setup['volunteer'])
        ->get(route('registrations.show', $reg->id))
        ->assertOk()
        ->assertSee('Tarik pendaftaran');
});
