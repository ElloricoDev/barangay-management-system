<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('admin account page is displayed', function () {
    $user = User::factory()->create([
        'role' => 'super_admin',
    ]);

    $this->actingAs($user)->get('/admin/account')->assertOk();
});

test('admin account profile can be updated', function () {
    $user = User::factory()->create([
        'role' => 'super_admin',
    ]);

    $response = $this
        ->actingAs($user)
        ->from('/admin/account')
        ->put('/admin/account/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/account');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $response->assertSessionHas('success');
});

test('admin account password can be updated', function () {
    $user = User::factory()->create([
        'role' => 'super_admin',
    ]);

    $response = $this
        ->actingAs($user)
        ->from('/admin/account')
        ->put('/admin/account/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/account');

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    $response->assertSessionHas('success');
});

test('admin account password requires correct current password', function () {
    $user = User::factory()->create([
        'role' => 'super_admin',
    ]);

    $response = $this
        ->actingAs($user)
        ->from('/admin/account')
        ->put('/admin/account/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/admin/account');
});

test('admin can delete own account with current password', function () {
    $user = User::factory()->create([
        'role' => 'super_admin',
    ]);

    $response = $this
        ->actingAs($user)
        ->from('/admin/account')
        ->delete('/admin/account', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('admin account deletion requires correct password', function () {
    $user = User::factory()->create([
        'role' => 'super_admin',
    ]);

    $response = $this
        ->actingAs($user)
        ->from('/admin/account')
        ->delete('/admin/account', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/admin/account');

    $this->assertNotNull($user->fresh());
});
