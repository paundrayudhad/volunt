<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

it('membatasi request submit registrasi hingga 10 per menit', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'registration_open']);

    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit('registration-submit:'.$user->id);
    }

    expect(RateLimiter::tooManyAttempts('registration-submit:'.$user->id, 10))->toBeTrue();
});

it('membatasi request ekspor data hingga 10 per menit', function () {
    $user = User::factory()->create();
    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit('exports:'.$user->id);
    }

    expect(RateLimiter::tooManyAttempts('exports:'.$user->id, 10))->toBeTrue();
});

it('membatasi request auth hingga 5 per menit', function () {
    $email = 'test@example.com';
    $ip = '127.0.0.1';
    $key = 'auth:'.$ip.'|'.$email;

    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit($key);
    }

    expect(RateLimiter::tooManyAttempts($key, 5))->toBeTrue();
});

it('membatasi request public api hingga 60 per menit', function () {
    $ip = '127.0.0.1';
    $key = 'public-api:'.$ip;

    for ($i = 0; $i < 60; $i++) {
        RateLimiter::hit($key);
    }

    expect(RateLimiter::tooManyAttempts($key, 60))->toBeTrue();
});
