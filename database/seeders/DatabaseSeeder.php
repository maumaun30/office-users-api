<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed local development accounts. All passwords are "password".
     *
     * Avoids factories so it also runs in production images, where Faker
     * (a dev dependency) isn't installed.
     */
    public function run(): void
    {
        $accounts = [
            ['Admin User', 'admin@example.com', Role::Admin],
            ['Staff User', 'staff@example.com', Role::Staff],
            ['Inventory Service', 'inventory-service@example.com', Role::Service],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->fill(['name' => $name, 'password' => 'password']);
            $user->role = $role;
            $user->email_verified_at ??= now();
            $user->save();
        }
    }
}
