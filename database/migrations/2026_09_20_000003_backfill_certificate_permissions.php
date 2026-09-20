<?php

use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Memberi permission certificate.* ke owner aktif yang sudah ada
     * sebelum permission tersebut diperkenalkan (Phase 5A). Idempoten:
     * aman dijalankan ulang maupun sebelum/sesudah PermissionSeeder.
     */
    public function up(): void
    {
        $perms = ['certificate.issue', 'certificate.revoke', 'certificate.read'];
        $ada = Permission::whereIn('name', $perms)->pluck('name')->all();
        if (count($ada) !== count($perms)) {
            return;
        }
        OrganizationMember::where('role', 'owner')
            ->where('status', 'active')
            ->distinct()
            ->pluck('user_id')
            ->each(function (int $userId) use ($perms): void {
                $user = User::whereKey($userId)->first();
                if ($user !== null) {
                    $user->givePermissionTo($perms);
                }
            });
    }

    /**
     * Hanya mencabut dari user yang kini BUKAN owner aktif — tidak pernah
     * mencabut hak yang masih valid.
     */
    public function down(): void
    {
        $perms = ['certificate.issue', 'certificate.revoke', 'certificate.read'];
        $ownerAktif = OrganizationMember::where('role', 'owner')
            ->where('status', 'active')
            ->distinct()
            ->pluck('user_id')
            ->all();
        User::whereNotIn('id', $ownerAktif === [] ? [0] : $ownerAktif)
            ->each(function (User $user) use ($perms): void {
                $user->revokePermissionTo($perms);
            });
    }
};
