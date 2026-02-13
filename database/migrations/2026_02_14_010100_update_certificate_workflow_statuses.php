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
        DB::statement("ALTER TABLE certificates MODIFY COLUMN status ENUM('pending', 'submitted', 'ready_for_approval', 'approved', 'rejected', 'release', 'released') NOT NULL DEFAULT 'submitted'");
        DB::statement("UPDATE certificates SET status = 'submitted' WHERE status = 'pending'");
        DB::statement("UPDATE certificates SET status = 'released' WHERE status = 'release'");
        DB::statement("ALTER TABLE certificates MODIFY COLUMN status ENUM('submitted', 'ready_for_approval', 'approved', 'rejected', 'released') NOT NULL DEFAULT 'submitted'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE certificates MODIFY COLUMN status ENUM('submitted', 'ready_for_approval', 'approved', 'rejected', 'released', 'pending', 'release') NOT NULL DEFAULT 'pending'");
        DB::statement("UPDATE certificates SET status = 'pending' WHERE status = 'submitted'");
        DB::statement("UPDATE certificates SET status = 'release' WHERE status = 'released'");
        DB::statement("ALTER TABLE certificates MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'release') NOT NULL DEFAULT 'pending'");
    }
};

