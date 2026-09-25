<?php

use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Memberi permission talent.search dan talent.invite ke semua pemilik organisasi aktif
     * yang sudah ada sebelum permission tersebut diperkenalkan (Phase 5D).
     * Idempoten: aman dijalankan ulang maupun sebelum/sesudah PermissionSeeder.
     */
    public function up(): void
    {
        $ownerPerms = ['talent.search', 'talent.invite'];
        foreach ($ownerPerms as $nama) {
            Permission::findOrCreate($nama, 'web');
        }

        OrganizationMember::where('role', 'owner')
            ->where('status', 'active')
            ->distinct()
            ->pluck('user_id')
            ->each(function (int $userId) use ($ownerPerms): void {
                $user = User::whereKey($userId)->first();
                if ($user !== null) {
                    $user->givePermissionTo($ownerPerms);
                }
            });
    }

    /**
     * Mencabut permission talent dari akun yang bukan owner aktif.
     */
    public function down(): void
    {
        $ownerAktif = OrganizationMember::where('role', 'owner')
            ->where('status', 'active')
            ->distinct()
            ->pluck('user_id')
            ->all();

        $fallback = [0];
        User::whereNotIn('id', $ownerAktif === [] ? $fallback : $ownerAktif)
            ->each(function (User $user): void {
                $user->revokePermissionTo(['talent.search', 'talent.invite']);
            });
    }
};
