<?php

namespace Database\Seeders;

use App\Models\DelegationSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DelegationSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminId = User::query()->where('role', 'super_admin')->value('id');

        DelegationSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'staff_can_approve' => false,
                'enabled_by' => $adminId,
                'enabled_at' => now()->subDays(1),
            ]
        );
    }
}
