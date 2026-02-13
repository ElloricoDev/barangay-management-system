<?php

use App\Models\Document;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

it('allows super admin to approve and reject documents', function () {
    $admin = User::factory()->create([
        'role' => 'super_admin',
    ]);

    $uploader = User::factory()->create([
        'role' => 'staff_user',
    ]);

    RolePermission::query()->updateOrCreate(
        ['role' => 'super_admin'],
        [
            'permissions' => ['dashboard.view', 'documents.approve'],
            'updated_by' => $admin->id,
        ]
    );
    RolePermission::forgetCache('super_admin');

    $document = Document::query()->create([
        'uploaded_by' => $uploader->id,
        'title' => 'ID Proof',
        'module' => 'resident',
        'original_name' => 'id-proof.pdf',
        'stored_name' => 'id-proof.pdf',
        'disk' => 'public',
        'path' => 'documents/id-proof.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'status' => 'submitted',
    ]);

    $this->actingAs($admin)
        ->patch("/admin/documents/{$document->id}/approve")
        ->assertStatus(302)
        ->assertSessionHas('success');

    $document->refresh();
    expect($document->status)->toBe('approved');
    expect($document->reviewed_by)->toBe($admin->id);
    expect($document->reviewed_at)->not->toBeNull();

    $this->actingAs($admin)
        ->patch("/admin/documents/{$document->id}/reject", [
            'reason' => 'Missing signature',
        ])
        ->assertSessionHas('success');

    $document->refresh();
    expect($document->status)->toBe('rejected');
    expect($document->rejection_reason)->toBe('Missing signature');
});

it('forbids staff from approving documents', function () {
    $staff = User::factory()->create([
        'role' => 'staff_user',
    ]);

    RolePermission::query()->updateOrCreate(
        ['role' => 'staff_user'],
        [
            'permissions' => ['dashboard.view', 'documents.upload'],
            'updated_by' => $staff->id,
        ]
    );
    RolePermission::forgetCache('staff_user');

    $document = Document::query()->create([
        'uploaded_by' => $staff->id,
        'title' => 'Barangay Form',
        'module' => 'other',
        'original_name' => 'form.txt',
        'stored_name' => 'form.txt',
        'disk' => 'public',
        'path' => 'documents/form.txt',
        'mime_type' => 'text/plain',
        'file_size' => 64,
        'status' => 'submitted',
    ]);

    $this->actingAs($staff)
        ->patch("/admin/documents/{$document->id}/approve")
        ->assertStatus(403);
});
