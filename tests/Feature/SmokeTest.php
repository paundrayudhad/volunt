<?php

use Illuminate\Support\Facades\DB;

it('memuat halaman utama', function () {
    $this->get('/')->assertOk();
});

it('terhubung ke PostgreSQL', function () {
    expect(DB::select('select version()'))->not->toBeEmpty();
});
