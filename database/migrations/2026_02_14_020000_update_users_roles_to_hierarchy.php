<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role ENUM(
                'admin',
                'captain',
                'secretary',
                'staff',
                'super_admin',
                'records_manager',
                'finance_officer',
                'committee_access',
                'youth_admin',
                'encoder',
                'data_manager',
                'system_admin',
                'frontline_user'
            ) NOT NULL DEFAULT 'encoder'"
        );

        DB::statement("UPDATE users SET role = 'super_admin' WHERE role IN ('admin', 'captain')");
        DB::statement("UPDATE users SET role = 'records_manager' WHERE role = 'secretary'");
        DB::statement("UPDATE users SET role = 'encoder' WHERE role = 'staff'");

        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role ENUM(
                'super_admin',
                'records_manager',
                'finance_officer',
                'committee_access',
                'youth_admin',
                'encoder',
                'data_manager',
                'system_admin',
                'frontline_user'
            ) NOT NULL DEFAULT 'encoder'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role ENUM(
                'super_admin',
                'records_manager',
                'finance_officer',
                'committee_access',
                'youth_admin',
                'encoder',
                'data_manager',
                'system_admin',
                'frontline_user',
                'captain',
                'secretary',
                'staff'
            ) NOT NULL DEFAULT 'staff'"
        );

        DB::statement("UPDATE users SET role = 'captain' WHERE role = 'super_admin'");
        DB::statement("UPDATE users SET role = 'secretary' WHERE role = 'records_manager'");
        DB::statement("UPDATE users SET role = 'staff' WHERE role IN ('encoder', 'data_manager', 'system_admin', 'frontline_user', 'finance_officer', 'committee_access', 'youth_admin')");

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('captain', 'secretary', 'staff') NOT NULL DEFAULT 'staff'");
    }
};

