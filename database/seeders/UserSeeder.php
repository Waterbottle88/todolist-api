<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * @return void
     */
    public function run(): void
    {
        $named = [
            [
                'name'  => 'Admin User',
                'email' => 'admin@todoapi.com',
            ],
            [
                'name'  => 'Test User',
                'email' => 'test@example.com',
            ],
            [
                'name'  => 'Demo User',
                'email' => 'demo@todoapi.com',
            ],
        ];

        foreach ($named as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
        }

        User::factory(5)->create();
    }
}
