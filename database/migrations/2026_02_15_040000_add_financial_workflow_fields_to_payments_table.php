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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('transaction_type', 20)->default('revenue')->after('or_number');
            $table->string('revenue_source', 100)->nullable()->after('transaction_type');
            $table->string('expense_type', 100)->nullable()->after('revenue_source');
            $table->string('workflow_status', 30)->default('paid')->after('expense_type');
            $table->string('request_reference', 120)->nullable()->after('workflow_status');
            $table->string('voucher_number', 120)->nullable()->after('request_reference');
            $table->foreignId('approved_by')->nullable()->after('collected_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'transaction_type',
                'revenue_source',
                'expense_type',
                'workflow_status',
                'request_reference',
                'voucher_number',
                'approved_at',
            ]);
        });
    }
};
