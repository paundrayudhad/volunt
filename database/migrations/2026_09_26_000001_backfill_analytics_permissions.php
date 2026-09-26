<?php

use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    private const PERMISSIONS = [
        'analytics.view',
        'analytics.export',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $ownerUserIds = OrganizationMember::query()
            ->where('role', 'owner')
            ->where('status', 'active')
            ->pluck('user_id')
            ->unique();

        foreach (User::whereIn('id', $ownerUserIds)->cursor() as $user) {
            $user->givePermissionTo(self::PERMISSIONS);
        }
    }

    public function down(): void
    {
        $ownerUserIds = OrganizationMember::query()
            ->where('role', 'owner')
            ->where('status', 'active')
            ->pluck('user_id')
            ->unique();

        foreach (User::whereIn('id', $ownerUserIds)->cursor() as $user) {
            foreach (self::PERMISSIONS as $perm) {
                if ($user->hasPermissionTo($perm)) {
                    $user->revokePermissionTo($perm);
                }
            }
        }
    }
};
