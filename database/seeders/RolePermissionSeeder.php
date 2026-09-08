<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'events.manage',
            'media.manage',
            'orders.view',
            'photographers.manage',
            'settings.manage',
            'coupons.manage',
            'activity.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $superadmin = Role::findOrCreate(User::ROLE_SUPERADMIN);
        $superadmin->syncPermissions($permissions);

        $admin = Role::findOrCreate(User::ROLE_ADMIN);
        $admin->syncPermissions(['events.manage', 'media.manage', 'orders.view', 'coupons.manage']);

        $photographer = Role::findOrCreate(User::ROLE_PHOTOGRAPHER);
        $photographer->syncPermissions(['media.manage']);

        // Esemeny-szervezo: sajat portal (read-only riportok), semmi kozvetlen kezelesi jog.
        Role::findOrCreate(User::ROLE_ORGANIZER);
    }
}
