<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckProductionCommand extends Command
{
    protected $signature = 'app:check-production';

    protected $description = 'Audit kesiapan deployment dan konfigurasi keamanan produksi';

    public function handle(): int
    {
        $this->info('=== Production Readiness Checklist ===');
        $checks = [];
        $failed = false;

        // 1. APP_DEBUG
        $debug = (bool) config('app.debug');
        $checks[] = [
            'Check' => 'APP_DEBUG is disabled',
            'Status' => $debug ? '<fg=red>FAIL</>' : '<fg=green>OK</>',
            'Details' => $debug ? 'APP_DEBUG=true (harus false di produksi)' : 'Disabled',
        ];
        if ($debug && app()->environment('production')) {
            $failed = true;
        }

        // 2. APP_KEY
        $hasKey = ! empty(config('app.key'));
        $checks[] = [
            'Check' => 'Encryption Key (APP_KEY)',
            'Status' => $hasKey ? '<fg=green>OK</>' : '<fg=red>FAIL</>',
            'Details' => $hasKey ? 'Configured' : 'Missing APP_KEY',
        ];
        if (! $hasKey) {
            $failed = true;
        }

        // 3. Database Connection
        $dbOk = true;
        $dbError = '';
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbOk = false;
            $dbError = $e->getMessage();
        }
        $checks[] = [
            'Check' => 'Database Connection',
            'Status' => $dbOk ? '<fg=green>OK</>' : '<fg=red>FAIL</>',
            'Details' => $dbOk ? 'Connected (PostgreSQL)' : $dbError,
        ];
        if (! $dbOk) {
            $failed = true;
        }

        // 4. Storage & Cache Permissions
        $storageWritable = is_writable(storage_path('framework')) && is_writable(storage_path('logs'));
        $checks[] = [
            'Check' => 'Storage Permissions',
            'Status' => $storageWritable ? '<fg=green>OK</>' : '<fg=red>FAIL</>',
            'Details' => $storageWritable ? 'Writable' : 'storage/ not writable',
        ];
        if (! $storageWritable) {
            $failed = true;
        }

        // 5. Session Cookie Security
        $secureCookie = (bool) config('session.secure');
        $checks[] = [
            'Check' => 'Session Secure Cookie',
            'Status' => $secureCookie ? '<fg=green>OK</>' : '<fg=yellow>WARN</>',
            'Details' => $secureCookie ? 'Enabled' : 'SESSION_SECURE_COOKIE=false (disarankan true di HTTPS)',
        ];

        $this->table(['Check', 'Status', 'Details'], $checks);

        if ($failed) {
            $this->error('Terdapat pemeriksaan kesiapan produksi yang gagal.');

            return self::FAILURE;
        }

        $this->info('Semua pemeriksaan utama kesiapan produksi berhasil.');

        return self::SUCCESS;
    }
}
