<?php

use Illuminate\Support\Facades\Artisan;

it('menjalankan command app:check-production dan memvalidasi konfigurasi dasar', function () {
    $code = Artisan::call('app:check-production');
    $output = Artisan::output();

    expect($output)->toContain('Production Readiness Checklist');
    expect($output)->toContain('Encryption Key');
    expect($output)->toContain('Database Connection');
    expect($output)->toContain('Storage Permissions');
});
