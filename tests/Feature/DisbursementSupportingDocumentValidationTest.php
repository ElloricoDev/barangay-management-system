<?php

use App\Models\DisbursementRequest;
use App\Models\Document;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

it('requires an approved request document when creating a disbursement request', function () {
    $financeOfficer = User::factory()->create([
        'role' => 'finance_officer',
    ]);

    RolePermission::query()->updateOrCreate(
        ['role' => 'finance_officer'],
        [
            'permissions' => ['dashboard.view', 'finance.disbursement.request'],
            'updated_by' => $financeOfficer->id,
        ]
    );

    $this->actingAs($financeOfficer)
        ->post('/admin/disbursement-requests', [
            'expense_type' => 'operations',
            'purpose' => 'Office supplies restock',
            'amount' => 1000,
        ])
        ->assertStatus(302)
        ->assertSessionHasErrors('request_document_id');
});

it('requires an approved voucher document when releasing an approved disbursement request', function () {
    $financeOfficer = User::factory()->create([
        'role' => 'finance_officer',
    ]);

    RolePermission::query()->updateOrCreate(
        ['role' => 'finance_officer'],
        [
            'permissions' => ['dashboard.view', 'finance.disbursement.release'],
            'updated_by' => $financeOfficer->id,
        ]
    );

    $requestDocument = Document::query()->create([
        'uploaded_by' => $financeOfficer->id,
        'title' => 'Approved Request Memo',
        'module' => 'financial',
        'original_name' => 'request-memo.pdf',
        'stored_name' => 'request-memo.pdf',
        'disk' => 'public',
        'path' => 'documents/request-memo.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1536,
        'status' => 'approved',
    ]);

    $disbursementRequest = DisbursementRequest::query()->create([
        'budget_allocation_id' => null,
        'request_document_id' => $requestDocument->id,
        'request_reference' => 'DR-VAL-2026-001',
        'expense_type' => 'operations',
        'purpose' => 'Fuel allowance',
        'amount' => 1200,
        'status' => 'approved',
        'requested_by' => $financeOfficer->id,
        'requested_at' => now(),
        'approved_by' => $financeOfficer->id,
        'approved_at' => now(),
        'rejected_by' => null,
        'rejected_at' => null,
        'released_payment_id' => null,
        'voucher_number' => null,
        'voucher_document_id' => null,
        'remarks' => null,
        'rejection_reason' => null,
    ]);

    $this->actingAs($financeOfficer)
        ->patch("/admin/disbursement-requests/{$disbursementRequest->id}/release", [
            'or_number' => 'OR-VAL-2026-001',
            'description' => 'Fuel disbursement release',
        ])
        ->assertStatus(302)
        ->assertSessionHasErrors('voucher_document_id');
});
