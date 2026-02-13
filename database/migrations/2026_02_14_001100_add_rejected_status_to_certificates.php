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
        DB::statement("ALTER TABLE certificates MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'release') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE certificates SET status = 'pending' WHERE status = 'rejected'");
        DB::statement("ALTER TABLE certificates MODIFY COLUMN status ENUM('pending', 'approved', 'release') NOT NULL DEFAULT 'pending'");
    }
};

