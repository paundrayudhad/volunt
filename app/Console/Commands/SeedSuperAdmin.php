<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedSuperAdmin extends Command
{
    protected $signature = 'app:seed-super-admin {--email=} {--name=Administrator}';

    protected $description = 'Buat atau pastikan user super_admin (idempotent)';

    public function handle(AuditLogService $audit): int
    {
        $email = $this->option('email') ?: (string) config('auth.super_admin_email', 'admin@webvolunteer.local');
        $name = (string) $this->option('name');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Str::random(32),
                'email_verified_at' => now(),
            ]);
        }

        if ($user->hasRole('super_admin')) {
            $this->info("Super admin {$email} sudah ada.");

            return self::SUCCESS;
        }

        $user->assignRole('super_admin');

        $audit->record($user, 'super_admin.seeded', User::class, $user->id, [
            'new' => ['email' => $user->email],
        ]);

        $this->info("Super admin {$email} berhasil dibuat.");

        return self::SUCCESS;
    }
}
