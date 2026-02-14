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
        Schema::create('fund_adjustments', function (Blueprint $table) {
            $table->id();
            $table->enum('adjustment_type', ['credit', 'debit']);
            $table->decimal('amount', 14, 2);
            $table->string('reason');
            $table->string('reference_no')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('adjusted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('adjusted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_adjustments');
    }
};
