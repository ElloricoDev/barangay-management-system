<?php

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses role permission overrides when checking user permissions', function () {
    $user = User::factory()->create([
        'role' => 'technical_administrator',
    ]);

    expect($user->hasPermission('system.settings'))->toBeTrue();

    RolePermission::query()->updateOrCreate(
        ['role' => 'technical_administrator'],
        [
            'permissions' => ['dashboard.view'],
            'updated_by' => $user->id,
        ]
    );
    RolePermission::forgetCache('technical_administrator');

    $user->refresh();

    expect($user->hasPermission('system.settings'))->toBeFalse();
    expect($user->hasPermission('dashboard.view'))->toBeTrue();
});
