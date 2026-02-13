<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exports reports analytics csv for authorized users', function () {
    $user = User::factory()->create([
        'role' => 'super_admin',
    ]);

    $response = $this->actingAs($user)->get('/admin/reports/export');

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
