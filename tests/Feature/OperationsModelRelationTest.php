<?php

use App\Models\Assignment;
use App\Models\EventShift;
use App\Models\Registration;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;

it('registrasi memiliki satu assignment', function (): void {
    $reg = Registration::factory()->create();

    expect($reg->assignment)->toBeNull();

    Assignment::factory()->create(['registration_id' => $reg->id]);

    expect($reg->refresh()->assignment)->not->toBeNull()
        ->and($reg->assignment->registration_id)->toBe($reg->id);
});

it('permission operations tersedia setelah seed', function (): void {
    $this->seed(PermissionSeeder::class);

    foreach (['assignment.manage', 'assignment.read', 'attendance.record', 'attendance.read', 'announcement.publish', 'announcement.read'] as $p) {
        expect(Permission::findByName($p, 'web'))->not->toBeNull();
    }
});

it('assignment transitions const valid', function (): void {
    expect(Assignment::TRANSITIONS['assigned'])->toContain('confirmed')
        ->and(Assignment::TRANSITIONS['completed'])->toBe([])
        ->and(Assignment::TRANSITIONS['cancelled'])->toBe([]);
});

it('shift memiliki counter filled_count default 0', function (): void {
    $shift = EventShift::factory()->create();

    expect($shift->filled_count)->toBe(0);
});
