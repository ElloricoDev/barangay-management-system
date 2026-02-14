<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->string('budget_type', 20)->default('annual')->after('fiscal_year');
            $table->string('resolution_no', 120)->nullable()->after('program_name');
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->index(['fiscal_year', 'budget_type', 'status'], 'budget_allocations_workflow_idx');
        });

        DB::table('budget_allocations')
            ->where('status', 'active')
            ->update(['status' => 'approved']);

        DB::table('budget_allocations')
            ->where('status', 'archived')
            ->update(['status' => 'rejected']);

        DB::table('budget_allocations')
            ->whereNotIn('status', ['draft', 'for_council_approval', 'approved', 'rejected'])
            ->update(['status' => 'draft']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('budget_allocations')
            ->whereIn('status', ['draft', 'for_council_approval', 'approved'])
            ->update(['status' => 'active']);

        DB::table('budget_allocations')
            ->where('status', 'rejected')
            ->update(['status' => 'archived']);

        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->dropIndex('budget_allocations_workflow_idx');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'budget_type',
                'resolution_no',
                'submitted_at',
                'approved_at',
                'rejection_reason',
            ]);
        });
    }
};
