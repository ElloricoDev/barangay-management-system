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
        Schema::create('financial_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('agency', 20);
            $table->string('report_type', 60);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('reference_no', 120)->unique();
            $table->string('status', 30)->default('draft');
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['agency', 'status'], 'financial_submissions_agency_status_idx');
            $table->index(['report_type', 'period_end'], 'financial_submissions_report_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_submissions');
    }
};
