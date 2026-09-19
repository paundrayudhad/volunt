<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'organization.view',
        'organization.update',
        'member.view',
        'member.invite',
        'member.remove',
        'member.change_role',
        'invitation.manage',
        'request.create',
        'request.review',
        'organization.suspend',
        'organization.archive',
        'audit.read',
        'security.read',
        'event.create',
        'event.view',
        'event.update',
        'event.delete',
        'event.publish',
        'division.manage',
        'role.manage',
        'shift.manage',
        'registration.read',
        'registration.review',
        'assignment.manage',
        'assignment.read',
        'attendance.record',
        'attendance.read',
        'announcement.publish',
        'announcement.read',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('super_admin', 'web');
    }
}
