<?php

namespace Database\Seeders;

use App\Models\BarangayProgram;
use App\Models\User;
use Illuminate\Database\Seeder;

class BarangayProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $creatorIds = User::query()->pluck('id')->all();
        if (empty($creatorIds)) {
            return;
        }

        $committees = [
            'Peace and Order',
            'Health',
            'Education',
            'Youth and Sports',
            'Infrastructure',
            'Environment',
        ];

        foreach (['youth', 'barangay'] as $category) {
            for ($i = 1; $i <= 20; $i++) {
                $start = fake()->dateTimeBetween('-8 months', '+2 months');
                $end = fake()->optional(0.8)->dateTimeBetween($start, '+4 months');

                BarangayProgram::query()->create([
                    'title' => ucfirst($category).' Program '.fake()->unique()->words(2, true),
                    'description' => fake()->paragraph(),
                    'category' => $category,
                    'committee' => fake()->randomElement($committees),
                    'status' => fake()->randomElement(['planned', 'ongoing', 'completed', 'cancelled']),
                    'start_date' => $start,
                    'end_date' => $end,
                    'budget' => fake()->optional(0.85)->randomFloat(2, 5000, 200000),
                    'participants' => fake()->numberBetween(5, 500),
                    'remarks' => fake()->optional(0.5)->sentence(8),
                    'created_by' => fake()->randomElement($creatorIds),
                ]);
            }
        }
    }
}
