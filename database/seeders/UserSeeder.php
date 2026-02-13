<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()
        ->count(10)
        ->create([
            'role' => 'staff'
            ]);

        User::updateOrCreate(
            ['email' => 'captain@gmail.com'],
            [
                'name' => 'Barangay Captain',
                'password' => Hash::make('captain123'),
                'role' => 'captain',
            ]
        );

        User::updateOrCreate(
            ['email' => 'secretary@gmail.com'],
            [
                'name' => 'Barangay Secretary',
                'password' => Hash::make('secretary123'),
                'role' => 'secretary',
            ]
        );
    }
}
