<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

it('updates system settings from admin settings screen', function () {
    $user = User::factory()->create([
        'role' => 'super_admin',
    ]);

    $response = $this->actingAs($user)->put('/admin/system-settings', [
        'barangay_name' => 'Barangay Test',
        'barangay_city' => 'Test City',
        'barangay_province' => 'Test Province',
        'contact_number' => '09991234567',
        'contact_email' => 'test@example.com',
        'receipt_prefix' => 'OR-T',
        'timezone' => 'Asia/Manila',
        'maintenance_mode' => true,
        'footer_note' => 'Footer test note',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHas('success');

    $settings = SystemSetting::current();
    expect($settings->barangay_name)->toBe('Barangay Test');
    expect($settings->maintenance_mode)->toBeTrue();
    expect($settings->receipt_prefix)->toBe('OR-T');
});
