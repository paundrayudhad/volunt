<?php

use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Memberi permission incident.* + lostfound.* ke owner aktif yang sudah ada
     * sebelum permission tersebut diperkenalkan (Phase 5B). Idempoten:
     * aman dijalankan ulang maupun sebelum/sesudah PermissionSeeder.
     */
    public function up(): void
    {
        $perms = ['incident.manage', 'incident.report', 'lostfound.manage'];
        foreach ($perms as $nama) {
            Permission::findOrCreate($nama, 'web');
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
     * Mencabut manage-perm (incident.manage, lostfound.manage) dari semua yang
     * kini bukan owner aktif, dan incident.report hanya dari yang kini BUKAN
     * staff aktif DAN bukan owner aktif — hak report sah milik staff posko
     * (spec §1) tidak pernah dicabut.
     */
    public function down(): void
    {
        $ownerAktif = OrganizationMember::where('role', 'owner')
            ->where('status', 'active')
            ->distinct()
            ->pluck('user_id')
            ->all();
        $stafAktif = OrganizationMember::where('role', 'staff')
            ->where('status', 'active')
            ->distinct()
            ->pluck('user_id')
            ->all();
        $fallback = [0];
        User::whereNotIn('id', $ownerAktif === [] ? $fallback : $ownerAktif)
            ->each(function (User $user): void {
                $user->revokePermissionTo(['incident.manage', 'lostfound.manage']);
            });
        $terlindungi = array_unique(array_merge($ownerAktif, $stafAktif));
        User::whereNotIn('id', $terlindungi === [] ? $fallback : $terlindungi)
            ->each(function (User $user): void {
                $user->revokePermissionTo('incident.report');
            });
    }
};
