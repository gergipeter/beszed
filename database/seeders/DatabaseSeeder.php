<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Child;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'parent@example.test'],
            ['name' => 'Demo Parent', 'password' => 'local-demo-password'],
        );

        Child::firstOrCreate(
            ['user_id' => $user->id],
            ['name' => 'Zoé'],
        );

        $this->call(BeszedContentSeeder::class);
    }
}
