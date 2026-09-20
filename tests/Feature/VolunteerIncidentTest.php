<?php

use App\Models\Event;
use App\Models\EventRole;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Services\LostFoundService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/** @return array{org: Organization, event: Event, vol: User, role: EventRole} */
function volInsSetup(): array
{
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create(['organization_id' => $org->id]);
    $vol = User::factory()->create();
    $role = EventRole::factory()->create(['event_id' => $event->id]);
    Registration::factory()->create([
        'user_id' => $vol->id,
        'event_id' => $event->id,
        'role_id' => $role->id,
        'status' => 'accepted',
    ]);

    return ['org' => $org, 'event' => $event, 'vol' => $vol->refresh(), 'role' => $role];
}

function volInsFoto(): UploadedFile
{
    $biner = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $tmp = tempnam(sys_get_temp_dir(), 'foto');
    file_put_contents($tmp, $biner);

    return new UploadedFile($tmp, 'barang.png', 'image/png', null, true);
}

it('volunteer lapor insiden di event yang ia ikuti', function () {
    $s = volInsSetup();

    $this->actingAs($s['vol'])->post(route('my.incidents.store'), [
        'event_id' => $s['event']->id,
        'category' => 'medical',
        'priority' => 'high',
        'location' => 'Depan panggung',
        'description' => 'Penonton jatuh pingsan di depan panggung.',
    ])->assertRedirect();

    expect(Incident::where('reporter_id', $s['vol']->id)->count())->toBe(1);
});

it('volunteer tak bisa lapor di event yang tak ia ikuti', function () {
    $s = volInsSetup();
    $eventLain = Event::factory()->create();

    $this->actingAs($s['vol'])->post(route('my.incidents.store'), [
        'event_id' => $eventLain->id,
        'category' => 'medical',
        'priority' => 'high',
        'location' => 'Depan panggung',
        'description' => 'Penonton jatuh pingsan di depan panggung.',
    ])->assertNotFound();

    expect(Incident::where('reporter_id', $s['vol']->id)->count())->toBe(0);
});

it('volunteer klaim barang found', function () {
    $s = volInsSetup();
    $pelapor = User::factory()->create();
    $item = app(LostFoundService::class)->report($s['event'], $pelapor, [
        'kind' => 'found',
        'item_name' => 'Kunci motor',
        'location' => 'Posko informasi',
    ]);

    $this->actingAs($s['vol'])->post(route('my.lost_found.claim', $item->id))->assertRedirect();

    expect($item->refresh()->status)->toBe('claimed')
        ->and($item->refresh()->claimant_id)->toBe($s['vol']->id);
});

it('klaim milik sendiri 422', function () {
    $s = volInsSetup();
    $item = app(LostFoundService::class)->report($s['event'], $s['vol'], [
        'kind' => 'found',
        'item_name' => 'Syal',
        'location' => 'Tribun',
    ]);

    $this->actingAs($s['vol'])->from(route('my.lost_found.index'))
        ->post(route('my.lost_found.claim', $item->id))
        ->assertRedirect(route('my.lost_found.index'));

    $this->actingAs($s['vol'])->get(route('my.lost_found.index'))->assertSee('sendiri');

    expect($item->refresh()->status)->toBe('found');
});

it('foto tanpa signature 403 dan dengan signature 200', function () {
    Storage::fake('local');
    $s = volInsSetup();
    $item = app(LostFoundService::class)->report($s['event'], $s['vol'], [
        'kind' => 'found',
        'item_name' => 'Dompet',
        'location' => 'Posko informasi',
        'photo' => volInsFoto(),
    ]);

    $this->actingAs($s['vol'])->get("/lost-found-photos/{$item->id}")->assertForbidden();

    $url = URL::signedRoute('lostfound.photo', ['lostFoundItem' => $item->id], now()->addMinutes(30));

    $this->actingAs($s['vol'])->get($url)->assertOk()->assertHeader('content-type', 'image/png');
});

it('foto lintas org 404', function () {
    Storage::fake('local');
    $s = volInsSetup();
    $orgLain = Organization::factory()->create(['status' => 'active']);
    $eventLain = Event::factory()->create(['organization_id' => $orgLain->id]);
    $pelaporLain = User::factory()->create();
    $item = app(LostFoundService::class)->report($eventLain, $pelaporLain, [
        'kind' => 'found',
        'item_name' => 'Topi',
        'location' => 'Pintu A',
        'photo' => volInsFoto(),
    ]);
    $url = URL::signedRoute('lostfound.photo', ['lostFoundItem' => $item->id], now()->addMinutes(30));

    $this->actingAs($s['vol'])->get($url)->assertNotFound();
});

it('halaman index volunteer tampil', function () {
    $s = volInsSetup();

    $this->actingAs($s['vol'])->get(route('my.incidents.index'))->assertOk();
    $this->actingAs($s['vol'])->get(route('my.lost_found.index'))->assertOk();
});

it('anggota org tanpa registration accepted tak bisa lapor via volunteer', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create(['organization_id' => $org->id]);
    $member = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $member->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    $this->actingAs($member->refresh())->post(route('my.incidents.store'), [
        'event_id' => $event->id,
        'category' => 'security',
        'location' => 'Pintu A',
        'description' => 'Keributan di antrean pintu masuk A.',
    ])->assertNotFound();
});

it('foto hilang di disk 404', function () {
    Storage::fake('local');
    $s = volInsSetup();
    $item = app(LostFoundService::class)->report($s['event'], $s['vol'], [
        'kind' => 'found',
        'item_name' => 'Dompet',
        'location' => 'Posko informasi',
        'photo' => volInsFoto(),
    ]);
    Storage::disk('local')->delete($item->photo_path);

    $url = URL::signedRoute('lostfound.photo', ['lostFoundItem' => $item->id], now()->addMinutes(30));

    $this->actingAs($s['vol'])->get($url)->assertNotFound();
});
