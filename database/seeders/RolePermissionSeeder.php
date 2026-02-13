<?php

namespace Database\Seeders;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $updatedBy = User::query()->where('role', 'technical_administrator')->value('id')
            ?? User::query()->where('role', 'super_admin')->value('id');

        $matrix = config('permissions.matrix', []);

        foreach ($matrix as $role => $permissions) {
            RolePermission::query()->updateOrCreate(
                ['role' => $role],
                [
                    'permissions' => array_values($permissions),
                    'updated_by' => $updatedBy,
                ]
            );
        }
    }
}
