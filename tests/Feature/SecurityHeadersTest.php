<?php

use Illuminate\Support\Facades\Route;

it('menyertakan security headers lengkap pada setiap respons HTTP', function () {
    Route::get('/test-security-headers', fn () => response('OK'));

    $response = $this->get('/test-security-headers');

    $response->assertStatus(200);
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
    expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'self'");
});

it('menyertakan header HSTS saat environment production atau HTTPS', function () {
    Route::get('/test-hsts', fn () => response('OK'));

    config(['app.env' => 'production']);
    $response = $this->get('/test-hsts');

    expect($response->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
});
