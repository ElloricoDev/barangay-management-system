<?php

namespace Database\Seeders;

use App\Models\Blotter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BlotterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Blotter::factory()
        ->count(10)
        ->create();
    }
}
