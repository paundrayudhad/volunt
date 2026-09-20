<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\IncidentService;
use Database\Seeders\PermissionSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/** @return array{0: Organization, 1: User, 2: Event} */
function buatPaketInsiden(): array
{
    $org = Organization::factory()->create();
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    $owner->givePermissionTo(['incident.manage', 'incident.report']);
    $event = Event::factory()->create(['organization_id' => $org->id]);

    return [$org, $owner, $event];
}

it('rantai maju selangkah dan mencatat history', function () {
    [$org, $owner, $event] = buatPaketInsiden();
    $svc = app(IncidentService::class);
    $insiden = $svc->report($event, $owner, ['category' => 'medical', 'priority' => 'high', 'location' => 'Panggung kiri', 'description' => 'Penonton pingsan di depan panggung.']);

    expect($insiden->status)->toBe('open');
    $tugas = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $tugas->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    $svc->assign($insiden, $owner, $tugas);
    $svc->transition($insiden->refresh(), $owner, 'in_progress');

    expect($insiden->refresh()->status)->toBe('in_progress')
        ->and($insiden->histories()->count())->toBe(2);
});

it('lompat langkah ditolak 422', function () {
    [$org, $owner, $event] = buatPaketInsiden();
    $svc = app(IncidentService::class);
    $insiden = $svc->report($event, $owner, ['category' => 'crowd', 'priority' => 'medium', 'location' => 'Pintu B', 'description' => 'Antrean menumpuk di pintu masuk B.']);

    $svc->transition($insiden, $owner, 'resolved');
})->throws(HttpException::class, 'Tahap berikutnya yang sah: assigned.');

it('reopen dari resolved kembali open', function () {
    [$org, $owner, $event] = buatPaketInsiden();
    $svc = app(IncidentService::class);
    $insiden = $svc->report($event, $owner, ['category' => 'technical', 'priority' => 'low', 'location' => 'FOH', 'description' => 'Mic cadangan mati saat cek suara.']);
    foreach (['assigned', 'in_progress', 'resolved'] as $tahap) {
        if ($tahap === 'assigned') {
            $svc->assign($insiden->refresh(), $owner, $owner);
        } else {
            $svc->transition($insiden->refresh(), $owner, $tahap);
        }
    }
    $svc->reopen($insiden->refresh(), $owner, 'Kerusakan muncul lagi saat gladi.');

    expect($insiden->refresh()->status)->toBe('open');
});

it('reopen dari open ditolak 422', function () {
    [$org, $owner, $event] = buatPaketInsiden();
    $svc = app(IncidentService::class);
    $insiden = $svc->report($event, $owner, ['category' => 'security', 'priority' => 'high', 'location' => 'Gerbang utama', 'description' => 'Ada penonton tanpa tiket memaksa masuk.']);

    $svc->reopen($insiden, $owner, 'Alasan yang cukup panjang.');
})->throws(HttpException::class, 'Hanya insiden resolved/closed yang dapat dibuka ulang.');

it('tugas dari luar organisasi ditolak 422', function () {
    [$org, $owner, $event] = buatPaketInsiden();
    $svc = app(IncidentService::class);
    $insiden = $svc->report($event, $owner, ['category' => 'other', 'priority' => 'low', 'location' => 'Parkir', 'description' => 'Lampu parkir padam sebagian.']);
    $luar = User::factory()->create();

    $svc->assign($insiden, $owner, $luar);
})->throws(HttpException::class, 'Petugas harus member organisasi yang sama.');

it('backfill memberi perm insiden ke owner lama', function () {
    [$org, $owner, $event] = buatPaketInsiden();
    $owner->revokePermissionTo(['incident.manage', 'incident.report', 'lostfound.manage']);
    expect($owner->refresh()->can('incident.manage'))->toBeFalse();

    (require base_path('database/migrations/2026_09_20_000006_backfill_incident_permissions.php'))->up();

    expect($owner->refresh()->can('incident.manage'))->toBeTrue()
        ->and($owner->can('incident.report'))->toBeTrue()
        ->and($owner->can('lostfound.manage'))->toBeTrue();
});
