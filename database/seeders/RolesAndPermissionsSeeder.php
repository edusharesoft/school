<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: syncs config/school.php into the permissions/roles tables.
 * Safe to run on every deployment (php artisan db:seed --class=RolesAndPermissionsSeeder --force).
 * System roles get the configured permissions *added*; permissions granted later through the UI are kept.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = [];
        foreach (config('school.permissions') as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                $all[] = $name;
                Permission::query()->updateOrCreate(['name' => $name, 'guard_name' => 'web'], ['module' => $module]);
            }
        }

        foreach (config('school.roles') as $name => $def) {
            $role = Role::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['label' => $def['label'], 'label_bn' => $def['label_bn'], 'scope' => $def['scope'], 'portal' => $def['portal'], 'is_system' => true]
            );

            $resolved = self::expandPatterns($def['permissions'], $all);
            $role->givePermissionTo(array_values(array_diff($resolved, $role->permissions->pluck('name')->all())));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Expand '*', 'module.*' and '!exclusion' patterns against the full permission list. */
    public static function expandPatterns(array $patterns, array $all): array
    {
        $include = [];
        $exclude = [];
        foreach ($patterns as $pattern) {
            if (str_starts_with($pattern, '!')) {
                $exclude[] = substr($pattern, 1);
            } else {
                $include[] = $pattern;
            }
        }

        $matches = fn (string $permission, array $list) => collect($list)->contains(fn ($p) => fnmatch($p, $permission));

        return array_values(array_filter($all, fn ($perm) => $matches($perm, $include) && ! $matches($perm, $exclude)));
    }
}
