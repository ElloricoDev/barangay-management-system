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

        $updatedBy = DB::table('users')
            ->whereIn('role', ['technical_administrator', 'super_admin'])
            ->value('id');

        $updates = [
            'super_admin' => [
                'finance.submissions.view',
                'finance.submissions.manage',
                'finance.submissions.review',
            ],
            'finance_officer' => [
                'finance.submissions.view',
                'finance.submissions.manage',
            ],
            'barangay_chairperson' => [
                'finance.submissions.view',
                'finance.submissions.review',
            ],
            'external_auditor' => [
                'dashboard.view',
                'financial_management.view',
                'finance.statements.view',
                'finance.submissions.view',
                'finance.submissions.review',
                'document_archive.view',
                'documents.download',
            ],
        ];

        foreach ($updates as $role => $requiredPermissions) {
            $record = DB::table('role_permissions')->where('role', $role)->first();

            if (! $record) {
                DB::table('role_permissions')->insert([
                    'role' => $role,
                    'permissions' => json_encode(array_values(array_unique($requiredPermissions))),
                    'updated_by' => $updatedBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
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

        $remove = [
            'finance.submissions.view',
            'finance.submissions.manage',
            'finance.submissions.review',
        ];

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

        DB::table('role_permissions')->where('role', 'external_auditor')->delete();
    }
};
