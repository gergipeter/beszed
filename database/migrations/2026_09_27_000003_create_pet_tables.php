<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beszed_pets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $t->string('name', 50);
            $t->string('species', 30)->default('bunny');
            $t->unsignedTinyInteger('generation')->default(1);
            $t->unsignedTinyInteger('hunger')->default(50);
            $t->unsignedTinyInteger('happiness')->default(50);
            $t->unsignedTinyInteger('energy')->default(80);
            $t->unsignedTinyInteger('hygiene')->default(80);
            $t->unsignedTinyInteger('health')->default(80);
            $t->unsignedSmallInteger('level')->default(1);
            $t->unsignedInteger('experience')->default(0);
            $t->timestamp('born_at')->nullable();
            $t->timestamp('last_fed_at')->nullable();
            $t->timestamp('last_played_at')->nullable();
            $t->timestamp('last_cleaned_at')->nullable();
            $t->boolean('is_alive')->default(true);
            $t->string('death_reason')->nullable();
            $t->timestamps();
            $t->index(['child_id', 'is_alive']);
        });

        Schema::create('beszed_pet_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pet_id')->constrained('beszed_pets')->cascadeOnDelete();
            $t->string('item_type', 30);
            $t->string('item_name', 50);
            $t->unsignedSmallInteger('quantity')->default(1);
            $t->enum('rarity', ['common', 'rare', 'epic', 'legendary'])->default('common');
            $t->timestamps();
            $t->unique(['pet_id', 'item_name']);
        });

        Schema::create('beszed_pet_meals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pet_id')->constrained('beszed_pets')->cascadeOnDelete();
            $t->enum('type', ['standard', 'nutritious', 'treat']);
            $t->unsignedTinyInteger('hunger_before');
            $t->timestamp('created_at');
            $t->index(['pet_id', 'created_at']);
        });

        Schema::create('beszed_pet_minigames', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pet_id')->constrained('beszed_pets')->cascadeOnDelete();
            $t->enum('game_type', ['catch', 'race', 'puzzle', 'memory']);
            $t->unsignedSmallInteger('score')->default(0);
            $t->boolean('won')->default(false);
            $t->json('reward')->nullable();
            $t->timestamps();
            $t->index(['pet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beszed_pet_minigames');
        Schema::dropIfExists('beszed_pet_meals');
        Schema::dropIfExists('beszed_pet_items');
        Schema::dropIfExists('beszed_pets');
    }
};
