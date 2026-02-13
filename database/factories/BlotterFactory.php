<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Blotter>
 */
class BlotterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'complainant_name' => fake()->name(),
            'respondent_name' => fake()->name(),
            'incident_date' => fake()->date(),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['ongoing', 'settled',]),
        ];
    }
}
