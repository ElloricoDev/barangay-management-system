<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

uses(RefreshDatabase::class);

test('email verification screen route is retired', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/verify-email')->assertNotFound();
});

test('email verification signed route is retired', function () {
    $user = User::factory()->unverified()->create();

    try {
        URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->fail('Expected RouteNotFoundException was not thrown.');
    } catch (\Throwable $e) {
        expect($e)->toBeInstanceOf(RouteNotFoundException::class);
    }
});
