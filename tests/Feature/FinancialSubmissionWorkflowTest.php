<?php

use App\Models\Document;
use App\Models\FinancialSubmission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

it('allows finance officer submission and external auditor acknowledgement workflow', function () {
    $financeOfficer = User::factory()->create([
        'role' => 'finance_officer',
    ]);
    $auditor = User::factory()->create([
        'role' => 'external_auditor',
    ]);

    RolePermission::query()->updateOrCreate(
        ['role' => 'finance_officer'],
        [
            'permissions' => ['dashboard.view', 'finance.submissions.view', 'finance.submissions.manage'],
            'updated_by' => $financeOfficer->id,
        ]
    );
    RolePermission::query()->updateOrCreate(
        ['role' => 'external_auditor'],
        [
            'permissions' => ['dashboard.view', 'finance.submissions.view', 'finance.submissions.review'],
            'updated_by' => $auditor->id,
        ]
    );

    $document = Document::query()->create([
        'uploaded_by' => $financeOfficer->id,
        'title' => 'Trial Balance FY 2026',
        'module' => 'financial',
        'original_name' => 'trial-balance-fy2026.pdf',
        'stored_name' => 'trial-balance-fy2026.pdf',
        'disk' => 'public',
        'path' => 'documents/trial-balance-fy2026.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'status' => 'approved',
    ]);

    $this->actingAs($financeOfficer)
        ->post('/admin/financial-submissions', [
            'agency' => 'coa',
            'report_type' => 'trial_balance',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'reference_no' => 'COA-TB-2026-01',
            'document_id' => $document->id,
            'remarks' => 'Initial filing',
        ])
        ->assertStatus(302)
        ->assertSessionHas('success');

    $submission = FinancialSubmission::query()->firstOrFail();
    expect($submission->status)->toBe('draft');

    $this->actingAs($financeOfficer)
        ->patch("/admin/financial-submissions/{$submission->id}/submit")
        ->assertStatus(302)
        ->assertSessionHas('success');

    $submission->refresh();
    expect($submission->status)->toBe('submitted');
    expect($submission->submitted_by)->toBe($financeOfficer->id);

    $this->actingAs($auditor)
        ->patch("/admin/financial-submissions/{$submission->id}/acknowledge", [
            'review_notes' => 'Received and reviewed.',
        ])
        ->assertStatus(302)
        ->assertSessionHas('success');

    $submission->refresh();
    expect($submission->status)->toBe('acknowledged');
    expect($submission->reviewed_by)->toBe($auditor->id);
});

it('requires review notes when returning a submitted financial submission', function () {
    $auditor = User::factory()->create([
        'role' => 'external_auditor',
    ]);

    RolePermission::query()->updateOrCreate(
        ['role' => 'external_auditor'],
        [
            'permissions' => ['dashboard.view', 'finance.submissions.view', 'finance.submissions.review'],
            'updated_by' => $auditor->id,
        ]
    );

    $document = Document::query()->create([
        'uploaded_by' => $auditor->id,
        'title' => 'SOE Jan 2026',
        'module' => 'financial',
        'original_name' => 'soe-jan-2026.pdf',
        'stored_name' => 'soe-jan-2026.pdf',
        'disk' => 'public',
        'path' => 'documents/soe-jan-2026.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'status' => 'approved',
    ]);

    $submission = FinancialSubmission::query()->create([
        'agency' => 'dbm',
        'report_type' => 'statement_of_expenditures',
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'reference_no' => 'DBM-SOE-2026-01',
        'status' => 'submitted',
        'document_id' => $document->id,
        'created_by' => $auditor->id,
        'submitted_by' => $auditor->id,
        'submitted_at' => now(),
    ]);

    $this->actingAs($auditor)
        ->patch("/admin/financial-submissions/{$submission->id}/return", [
            'review_notes' => '',
        ])
        ->assertStatus(302)
        ->assertSessionHasErrors('review_notes');
});
