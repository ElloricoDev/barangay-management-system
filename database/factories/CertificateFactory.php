<?php

namespace Database\Factories;

use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::query()->inRandomOrder()->value('id'),
            'type' => fake()->randomElement(['clearance', 'indigency', 'residency']),
            'purpose' => fake()->sentence(),
            'status' => fake()->randomElement(['submitted', 'ready_for_approval', 'approved', 'rejected', 'released']),
            'issue_date' => fake()->date(),
        ];
    }
}
