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
                'super_admin',
                'records_manager',
                'records_administrator',
                'finance_officer',
                'committee_access',
                'committee_access_user',
                'youth_admin',
                'youth_administrator',
                'staff_user',
                'encoder',
                'data_manager',
                'system_admin',
                'technical_administrator',
                'frontline_user'
            ) NOT NULL DEFAULT 'staff_user'"
        );

        DB::statement("UPDATE users SET role = 'records_administrator' WHERE role = 'records_manager'");
        DB::statement("UPDATE users SET role = 'committee_access_user' WHERE role = 'committee_access'");
        DB::statement("UPDATE users SET role = 'youth_administrator' WHERE role = 'youth_admin'");
        DB::statement("UPDATE users SET role = 'technical_administrator' WHERE role = 'system_admin'");
        DB::statement("UPDATE users SET role = 'staff_user' WHERE role = 'frontline_user'");
        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role ENUM(
                'super_admin',
                'records_administrator',
                'finance_officer',
                'committee_access_user',
                'youth_administrator',
                'staff_user',
                'data_manager',
                'encoder',
                'technical_administrator'
            ) NOT NULL DEFAULT 'staff_user'"
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
                'records_administrator',
                'finance_officer',
                'committee_access_user',
                'youth_administrator',
                'staff_user',
                'data_manager',
                'encoder',
                'technical_administrator',
                'records_manager',
                'committee_access',
                'youth_admin',
                'system_admin',
                'frontline_user'
            ) NOT NULL DEFAULT 'staff_user'"
        );

        DB::statement("UPDATE users SET role = 'records_manager' WHERE role = 'records_administrator'");
        DB::statement("UPDATE users SET role = 'committee_access' WHERE role = 'committee_access_user'");
        DB::statement("UPDATE users SET role = 'youth_admin' WHERE role = 'youth_administrator'");
        DB::statement("UPDATE users SET role = 'system_admin' WHERE role = 'technical_administrator'");
        DB::statement("UPDATE users SET role = 'frontline_user' WHERE role = 'staff_user'");

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
};
