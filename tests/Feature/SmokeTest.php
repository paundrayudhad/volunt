<?php

use Illuminate\Support\Facades\DB;

it('memuat halaman utama', function () {
    $this->get('/')->assertOk();
});

it('mengembalikan header X-Request-ID pada setiap respons', function () {
    $respon = $this->get('/');
    $respon->assertOk();
    $respon->assertHeader('X-Request-ID');

    $idPertama = $respon->headers->get('X-Request-ID');
    expect($idPertama)->not->toBeEmpty();

    $this->get('/', ['X-Request-ID' => 'uji-id-tetap'])
        ->assertOk()
        ->assertHeader('X-Request-ID', 'uji-id-tetap');
});

it('terhubung ke PostgreSQL', function () {
    expect(DB::select('select version()'))->not->toBeEmpty();
});
