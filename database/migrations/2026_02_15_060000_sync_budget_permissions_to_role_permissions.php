<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            return;
        }

        $updates = [
            'super_admin' => ['finance.budget.view', 'finance.budget.manage'],
            'finance_officer' => ['finance.budget.view', 'finance.budget.manage'],
            'barangay_chairperson' => ['finance.budget.view'],
        ];

        foreach ($updates as $role => $requiredPermissions) {
            $record = DB::table('role_permissions')->where('role', $role)->first();
            if (! $record) {
                continue;
            }

            $permissions = json_decode((string) $record->permissions, true);
            if (! is_array($permissions)) {
                $permissions = [];
            }

            $merged = array_values(array_unique(array_merge($permissions, $requiredPermissions)));

            DB::table('role_permissions')
                ->where('role', $role)
                ->update([
                    'permissions' => json_encode($merged),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            return;
        }

        $remove = ['finance.budget.view', 'finance.budget.manage'];
        $roles = ['super_admin', 'finance_officer', 'barangay_chairperson'];

        foreach ($roles as $role) {
            $record = DB::table('role_permissions')->where('role', $role)->first();
            if (! $record) {
                continue;
            }

            $permissions = json_decode((string) $record->permissions, true);
            if (! is_array($permissions)) {
                continue;
            }

            $filtered = array_values(array_filter($permissions, fn ($permission) => ! in_array($permission, $remove, true)));

            DB::table('role_permissions')
                ->where('role', $role)
                ->update([
                    'permissions' => json_encode($filtered),
                    'updated_at' => now(),
                ]);
        }
    }
};
