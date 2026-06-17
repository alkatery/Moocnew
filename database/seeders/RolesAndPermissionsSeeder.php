<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contexts\Identity\Domain\Permission as PermissionEnum;
use App\Contexts\Identity\Domain\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the five platform roles and the permission matrix (PRD §4).
 * Idempotent: safe to run repeatedly.
 */
final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::values() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (PermissionEnum::roleMatrix() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }

        // Ensure roles that carry no permissions still exist.
        foreach (RoleEnum::values() as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }
}
