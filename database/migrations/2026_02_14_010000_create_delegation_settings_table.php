<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delegation_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('staff_can_approve')->default(false);
            $table->foreignId('enabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegation_settings');
    }
};

