<?php

use App\Models\User;
use App\Models\RolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exports reports analytics csv for authorized users', function () {
    $user = User::factory()->create([
        'role' => 'super_admin',
    ]);
    RolePermission::query()->updateOrCreate(
        ['role' => 'super_admin'],
        [
            'permissions' => ['dashboard.view', 'reports.view'],
            'updated_by' => $user->id,
        ]
    );

    $response = $this->actingAs($user)->get('/admin/reports/export');

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
