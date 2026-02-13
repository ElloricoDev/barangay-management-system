<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('forgot password route is retired', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/forgot-password')->assertNotFound();
});

test('forgot password submit route is retired', function () {
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])->assertNotFound();
});

test('reset password routes are retired', function () {
    $this->get('/reset-password/test-token')->assertNotFound();
    $this->post('/reset-password', [
        'token' => 'test-token',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});
