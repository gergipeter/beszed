<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Speech recording with transcription and phoneme analysis
        Schema::create('beszed_speech_recordings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $t->string('game', 32)->nullable();
            $t->string('audio_path');
            $t->string('audio_url')->nullable();
            $t->text('transcription')->nullable();
            $t->text('phoneme_analysis')->nullable(); // JSON
            $t->unsignedTinyInteger('confidence_score')->nullable(); // 0-100
            $t->unsignedTinyInteger('pronunciation_score')->nullable(); // 0-100
            $t->unsignedTinyInteger('fluency_score')->nullable(); // 0-100
            $t->unsignedTinyInteger('clarity_score')->nullable(); // 0-100
            $t->json('feedback')->nullable(); // AI suggestions
            $t->timestamps();
            $t->index(['child_id', 'created_at']);
            $t->index(['game', 'pronunciation_score']);
        });

        // Phoneme-level tracking for targeted therapy
        Schema::create('beszed_phoneme_progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $t->string('phoneme', 10); // /s/, /r/, /th/, etc.
            $t->unsignedTinyInteger('accuracy')->default(0); // 0-100
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->unsignedSmallInteger('correct')->default(0);
            $t->unsignedSmallInteger('incorrect')->default(0);
            $t->timestamp('last_practiced_at')->nullable();
            $t->timestamps();
            $t->unique(['child_id', 'phoneme']);
            $t->index(['child_id', 'accuracy']);
        });

        // Adaptive difficulty scoring
        Schema::create('beszed_content_difficulty', function (Blueprint $t) {
            $t->id();
            $t->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $t->foreignId('content_item_id')->constrained('beszed_content_items')->nullOnDelete();
            $t->string('game', 32);
            $t->decimal('difficulty_rating', 5, 2)->default(1.0); // Elo-style rating
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->unsignedSmallInteger('successes')->default(0);
            $t->decimal('win_rate', 5, 2)->default(0.0);
            $t->timestamp('last_attempted_at')->nullable();
            $t->timestamps();
            $t->unique(['child_id', 'content_item_id']);
            $t->index(['child_id', 'game', 'difficulty_rating']);
        });

        // Progress predictions
        Schema::create('beszed_progress_predictions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $t->string('metric', 50); // 'pronunciation', 'fluency', 'vocabulary', etc.
            $t->unsignedTinyInteger('current_level')->default(0); // 0-100
            $t->unsignedTinyInteger('predicted_level')->default(0); // 0-100
            $t->unsignedSmallInteger('weeks_to_goal')->default(0);
            $t->decimal('confidence', 5, 2); // 0.0-1.0
            $t->json('historical_data')->nullable();
            $t->json('model_params')->nullable(); // ML model parameters
            $t->timestamp('predicted_at');
            $t->timestamps();
            $t->index(['child_id', 'metric', 'created_at']);
        });

        // Recommended games based on ML
        Schema::create('beszed_game_recommendations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $t->string('game', 32);
            $t->decimal('score', 5, 2); // 0-100, recommendation strength
            $t->string('reason', 100); // 'weakness', 'strength', 'fun', 'milestone', etc.
            $t->json('rationale')->nullable();
            $t->timestamp('recommended_at');
            $t->timestamps();
            $t->index(['child_id', 'recommended_at', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beszed_game_recommendations');
        Schema::dropIfExists('beszed_progress_predictions');
        Schema::dropIfExists('beszed_content_difficulty');
        Schema::dropIfExists('beszed_phoneme_progress');
        Schema::dropIfExists('beszed_speech_recordings');
    }
};
