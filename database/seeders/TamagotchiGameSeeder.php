<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TamagotchiGameSeeder extends Seeder
{
    public function run()
    {
        DB::table('games')->insertOrIgnore([
            'id' => 14,
            'name' => 'Tamagotchi',
            'engine' => 'tamagotchi',
            'category' => 'Care',
            'difficulty' => 'Medium',
            'icon' => '🐾',
            'duration_seconds' => 180,
            'description' => 'Raise and care for your virtual pet! Feed, play, and make it happy to level up.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
