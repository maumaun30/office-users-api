<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with local development accounts.
     * All passwords are "password".
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
        ]);

        User::factory()->service()->create([
            'name' => 'Inventory Service',
            'email' => 'inventory-service@example.com',
        ]);

        User::factory(5)->create();
    }
}
