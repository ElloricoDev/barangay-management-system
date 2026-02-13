<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $residentIds = Resident::query()->pluck('id')->all();
        $collectorIds = User::query()
            ->whereIn('role', ['super_admin', 'records_administrator', 'finance_officer', 'technical_administrator', 'staff_user', 'encoder'])
            ->pluck('id')
            ->all();

        if (empty($residentIds) || empty($collectorIds)) {
            return;
        }

        $serviceTypes = ['certificate', 'clearance_fee', 'blotter_fee', 'other_service'];
        $runKey = now()->format('YmdHis');

        for ($i = 1; $i <= 50; $i++) {
            Payment::query()->create([
                'resident_id' => fake()->randomElement($residentIds),
                'certificate_id' => null,
                'collected_by' => fake()->randomElement($collectorIds),
                'or_number' => 'OR-'.$runKey.'-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT).'-'.fake()->numberBetween(10, 99),
                'service_type' => fake()->randomElement($serviceTypes),
                'description' => fake()->sentence(4),
                'amount' => fake()->randomFloat(2, 50, 1500),
                'paid_at' => fake()->dateTimeBetween('-6 months', 'now'),
                'notes' => fake()->optional(0.6)->sentence(6),
            ]);
        }
    }
}
