<?php

use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Memberi permission artist.manage + artist.liaise ke owner aktif dan
     * artist.liaise ke volunteer accepted yang sudah ada sebelum permission
     * tersebut diperkenalkan (Phase 5C). Idempoten: aman dijalankan ulang
     * maupun sebelum/sesudah PermissionSeeder.
     */
    public function up(): void
    {
        $ownerPerms = ['artist.manage', 'artist.liaise'];
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
        $diterimaIds = Registration::where('status', 'accepted')
            ->distinct()
            ->pluck('user_id')
            ->all();
        User::whereIn('id', $diterimaIds === [] ? [0] : $diterimaIds)
            ->each(function (User $user): void {
                $user->givePermissionTo('artist.liaise');
            });
    }

    /**
     * Mencabut artist.manage dari semua yang kini bukan owner aktif, dan
     * artist.liaise hanya dari yang kini BUKAN volunteer-accepted DAN bukan
     * owner aktif — hak liaise sah milik volunteer diterima (spec §1) dan
     * owner tidak pernah dicabut.
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
                $user->revokePermissionTo('artist.manage');
            });
        $diterima = Registration::where('status', 'accepted')
            ->distinct()
            ->pluck('user_id')
            ->all();
        $terlindungi = array_unique(array_merge($ownerAktif, $diterima));
        User::whereNotIn('id', $terlindungi === [] ? $fallback : $terlindungi)
            ->each(function (User $user): void {
                $user->revokePermissionTo('artist.liaise');
            });
    }
};
