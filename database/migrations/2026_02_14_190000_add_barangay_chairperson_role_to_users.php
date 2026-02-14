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
                'barangay_chairperson',
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
        DB::statement("UPDATE users SET role = 'super_admin' WHERE role = 'barangay_chairperson'");

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
};
