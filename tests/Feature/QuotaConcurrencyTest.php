<?php

use App\Exceptions\QuotaFullException;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\QuotaService;
use App\Services\RegistrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** @return array{role: EventRole, event: Event} */
function regKonkurenPaket(int $kuota = 1): array
{
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create(['organization_id' => $org->id]);
    $event->forceFill(['status' => 'registration_open'])->save();
    $division = EventDivision::factory()->create(['event_id' => $event->id]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $division->id,
        'quota' => $kuota,
        'accepted_count' => 0,
    ]);

    return ['role' => $role->refresh(), 'event' => $event->refresh()];
}

function regKonkurenDaftar(array $paket, string $status = 'under_review'): Registration
{
    return Registration::unguarded(fn (): Registration => Registration::create([
        'user_id' => User::factory()->create()->id,
        'event_id' => $paket['event']->id,
        'role_id' => $paket['role']->id,
        'status' => $status,
        'submitted_at' => now(),
        'idempotency_key' => (string) Str::uuid(),
    ]))->refresh();
}

it('tiga accept konkuren pada kuota satu hanya meloloskan satu pemenang', function (): void {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl tidak tersedia');
    }
    $paket = regKonkurenPaket(1);
    expect($paket['role']->accepted_count)->toBe(0);

    $hasilDir = sys_get_temp_dir().'/quota-konkuren-'.getmypid();
    mkdir($hasilDir);
    $roleId = $paket['role']->id;
    $pids = [];

    foreach (range(1, 3) as $i) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->fail('pcntl_fork gagal membuat proses anak.');
        }
        if ($pid === 0) {
            DB::reconnect();
            $hasilFile = $hasilDir.'/anak-'.getmypid().'.txt';
            try {
                DB::transaction(fn () => app(QuotaService::class)->accept(EventRole::findOrFail($roleId)));
                file_put_contents($hasilFile, 'accepted');
            } catch (QuotaFullException) {
                file_put_contents($hasilFile, 'full');
            } catch (Throwable $e) {
                file_put_contents($hasilFile, 'error:'.$e->getMessage());
            }
            DB::disconnect();
            exit(0);
        }
        $pids[] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $hasil = [];
    foreach (glob($hasilDir.'/anak-*.txt') ?: [] as $file) {
        $hasil[] = trim((string) file_get_contents($file));
        unlink($file);
    }
    rmdir($hasilDir);

    $menang = count(array_filter($hasil, fn ($nilai) => $nilai === 'accepted'));
    $penuh = count(array_filter($hasil, fn ($nilai) => $nilai === 'full'));

    expect($menang)->toBe(1)
        ->and($penuh)->toBe(2)
        ->and($paket['role']->refresh()->accepted_count)->toBeLessThanOrEqual(1);
});

it('double accept berurutan hanya menaikkan counter satu kali', function (): void {
    $paket = regKonkurenPaket(1);
    $svc = app(QuotaService::class);

    $svc->accept($paket['role']);
    expect($paket['role']->refresh()->accepted_count)->toBe(1);

    try {
        $svc->accept($paket['role']->refresh());
        $this->fail('Seharusnya melempar QuotaFullException pada accept kedua.');
    } catch (QuotaFullException) {
        expect($paket['role']->refresh()->accepted_count)->toBe(1);
    }
});

it('accept lalu cancel mengembalikan counter ke nol', function (): void {
    $paket = regKonkurenPaket(1);
    $svc = app(RegistrationService::class);
    $owner = User::factory()->create();
    $reg = regKonkurenDaftar($paket);

    $svc->review($reg->refresh(), 'accepted', $owner);
    expect($paket['role']->refresh()->accepted_count)->toBe(1);

    $hasil = $svc->cancel($reg->refresh(), $owner, 'Slot dibatalkan panitia.');

    expect($hasil->status)->toBe('cancelled')
        ->and($paket['role']->refresh()->accepted_count)->toBe(0);
});
