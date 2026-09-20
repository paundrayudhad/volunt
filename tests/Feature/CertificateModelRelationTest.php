<?php

use App\Models\Certificate;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\QueryException;

it('sertTesRelasiSertifikat', function (): void {
    $sertifikat = Certificate::factory()->create();

    expect($sertifikat->event)->toBeInstanceOf(Event::class)
        ->and($sertifikat->user)->toBeInstanceOf(User::class)
        ->and($sertifikat->registration)->toBeInstanceOf(Registration::class)
        ->and($sertifikat->isRevoked())->toBeFalse();
});

it('sertTesAmbangDefaultNull', function (): void {
    $event = Event::factory()->create();

    expect($event->certificate_min_attendance_pct)->toBeNull();
});

it('sertTesCheckConstraintAmbang', function (): void {
    $event = Event::factory()->create();

    expect(fn () => $event->forceFill(['certificate_min_attendance_pct' => 0])->save())
        ->toThrow(QueryException::class);
    expect(fn () => $event->forceFill(['certificate_min_attendance_pct' => 101])->save())
        ->toThrow(QueryException::class);
});

it('sertTesUnikEventUser', function (): void {
    $sertifikat = Certificate::factory()->create();

    expect(fn () => Certificate::factory()->create([
        'event_id' => $sertifikat->event_id,
        'user_id' => $sertifikat->user_id,
    ]))->toThrow(QueryException::class);
});
