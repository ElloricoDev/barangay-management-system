<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

it('includes document files in backup snapshots', function () {
    Storage::fake('public');
    Storage::fake('local');

    $user = User::factory()->create([
        'role' => 'super_admin',
    ]);

    $path = 'documents/test-file.txt';
    Storage::disk('public')->put($path, 'backup file content');

    Document::query()->create([
        'resident_id' => null,
        'certificate_id' => null,
        'blotter_id' => null,
        'uploaded_by' => $user->id,
        'title' => 'Test Document',
        'module' => 'other',
        'original_name' => 'test-file.txt',
        'stored_name' => 'test-file.txt',
        'disk' => 'public',
        'path' => $path,
        'mime_type' => 'text/plain',
        'file_size' => Storage::disk('public')->size($path),
        'notes' => null,
    ]);

    $response = $this->actingAs($user)->post('/admin/backup-restore/create');
    $response->assertStatus(302);
    $response->assertSessionHas('success');

    $files = Storage::disk('local')->files('backups');
    expect($files)->not()->toBeEmpty();

    $payload = json_decode(Storage::disk('local')->get($files[0]), true);

    expect($payload)->toHaveKey('files.documents');
    expect($payload['files']['documents'])->not()->toBeEmpty();
    expect($payload['files']['documents'][0])->toHaveKey('content_base64');
});
