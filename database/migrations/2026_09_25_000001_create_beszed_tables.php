<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Betűvarázs may already have a children table — reuse it if so.
        if (! Schema::hasTable('children')) {
            Schema::create('children', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('name');
                $t->date('birth_date')->nullable();
                $t->timestamps();
            });
        }

        Schema::create('beszed_content_items', function (Blueprint $t) {
            $t->id();
            $t->string('game', 32)->index();
            $t->unsignedTinyInteger('level')->default(1);
            $t->json('payload');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index(['game', 'active', 'level']);
        });

        Schema::create('beszed_skill_levels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $t->string('game', 32);
            $t->unsignedTinyInteger('level');
            $t->unsignedTinyInteger('streak')->default(0);
            $t->timestamps();
            $t->unique(['child_id', 'game']);
        });

        Schema::create('beszed_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $t->string('game', 32);
            $t->foreignId('content_item_id')->nullable()->constrained('beszed_content_items')->nullOnDelete();
            $t->unsignedTinyInteger('level')->default(1);
            $t->boolean('correct');
            $t->unsignedTinyInteger('tries')->default(1);
            $t->unsignedInteger('duration_ms')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['child_id', 'game', 'created_at']);
        });

        Schema::create('beszed_recordings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('line_key', 64);
            $t->string('disk', 32);
            $t->string('path');
            $t->string('mime', 64)->nullable();
            $t->unsignedInteger('size')->default(0);
            $t->timestamps();
            $t->unique(['user_id', 'line_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beszed_recordings');
        Schema::dropIfExists('beszed_attempts');
        Schema::dropIfExists('beszed_skill_levels');
        Schema::dropIfExists('beszed_content_items');
        // children is intentionally left alone (may be shared with Betűvarázs).
    }
};
