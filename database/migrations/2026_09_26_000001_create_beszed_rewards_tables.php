<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per finished game: drives streaks, the daily goal, medals and stickers.
        Schema::create('beszed_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $t->string('game', 32);
            $t->unsignedTinyInteger('level')->default(1);
            $t->unsignedTinyInteger('rounds');
            $t->unsignedTinyInteger('correct');
            $t->unsignedTinyInteger('first_try');
            $t->unsignedInteger('duration_ms')->nullable();
            $t->timestamp('completed_at')->useCurrent();
            $t->index(['child_id', 'completed_at']);
            $t->index(['child_id', 'game']);
        });

        Schema::create('beszed_badges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $t->string('badge', 48);
            $t->timestamp('earned_at')->useCurrent();
            $t->unique(['child_id', 'badge']);
        });

        Schema::create('beszed_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('child_id')->unique()->constrained('children')->cascadeOnDelete();
            // One worn accessory per slot (head, face, extra), e.g. {"head":"hat","face":"glasses"}.
            $t->json('accessories')->nullable();
            // The sticker scene: chosen background + placed stickers ({badge, x, y, rotate}).
            $t->json('scene')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beszed_profiles');
        Schema::dropIfExists('beszed_badges');
        Schema::dropIfExists('beszed_sessions');
    }
};
