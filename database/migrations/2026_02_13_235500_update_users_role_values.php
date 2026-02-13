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
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'captain', 'secretary', 'staff') NOT NULL DEFAULT 'staff'");
        DB::statement("UPDATE users SET role = 'captain' WHERE role = 'admin'");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('captain', 'secretary', 'staff') NOT NULL DEFAULT 'staff'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'captain', 'secretary', 'staff') NOT NULL DEFAULT 'staff'");
        DB::statement("UPDATE users SET role = 'admin' WHERE role IN ('captain', 'secretary')");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff'");
    }
};
