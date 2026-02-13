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
            'role' => 'staff_user'
            ]);

        User::updateOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name' => 'Punong Barangay',
                'password' => Hash::make('superadmin123'),
                'role' => 'super_admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'secretary@gmail.com'],
            [
                'name' => 'Barangay Secretary',
                'password' => Hash::make('secretary123'),
                'role' => 'records_administrator',
            ]
        );

        User::updateOrCreate(
            ['email' => 'treasurer@gmail.com'],
            [
                'name' => 'Barangay Treasurer',
                'password' => Hash::make('treasurer123'),
                'role' => 'finance_officer',
            ]
        );

        User::updateOrCreate(
            ['email' => 'itadmin@gmail.com'],
            [
                'name' => 'IT Administrator',
                'password' => Hash::make('itadmin123'),
                'role' => 'technical_administrator',
            ]
        );

        User::updateOrCreate(
            ['email' => 'kagawad@gmail.com'],
            [
                'name' => 'Barangay Kagawad',
                'password' => Hash::make('kagawad123'),
                'role' => 'committee_access_user',
            ]
        );

        User::updateOrCreate(
            ['email' => 'skchair@gmail.com'],
            [
                'name' => 'SK Chairperson',
                'password' => Hash::make('skchair123'),
                'role' => 'youth_administrator',
            ]
        );

        User::updateOrCreate(
            ['email' => 'records@gmail.com'],
            [
                'name' => 'Records Officer',
                'password' => Hash::make('records123'),
                'role' => 'data_manager',
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@gmail.com'],
            [
                'name' => 'Administrative Staff',
                'password' => Hash::make('staff12345'),
                'role' => 'staff_user',
            ]
        );

        User::updateOrCreate(
            ['email' => 'encoder@gmail.com'],
            [
                'name' => 'Barangay Encoder',
                'password' => Hash::make('encoder123'),
                'role' => 'encoder',
            ]
        );
    }
}
