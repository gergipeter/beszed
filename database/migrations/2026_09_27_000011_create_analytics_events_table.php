<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('event_type'); // game_played, achievement_unlocked, level_up, etc.
            $table->string('category')->nullable(); // game, achievement, engagement
            $table->string('action')->nullable(); // start, complete, fail
            $table->json('properties')->nullable(); // extra data
            $table->string('session_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('event_type');
            $table->index('created_at');
            $table->index(['user_id', 'event_type', 'created_at']);
        });

        Schema::create('analytics_cohorts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('cohort_date');
            $table->integer('size')->default(0);
            $table->integer('d1_retention')->default(0); // day 1
            $table->integer('d7_retention')->default(0); // day 7
            $table->integer('d30_retention')->default(0); // day 30
            $table->integer('d90_retention')->default(0); // day 90
            $table->timestamps();
        });

        Schema::create('analytics_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('active_users')->default(0);
            $table->integer('new_users')->default(0);
            $table->integer('games_played')->default(0);
            $table->integer('sessions')->default(0);
            $table->integer('revenue')->default(0); // cents
            $table->decimal('average_session_duration', 8, 2)->default(0); // seconds
            $table->decimal('churn_rate', 5, 2)->default(0); // percentage
            $table->timestamps();

            $table->unique('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_metrics');
        Schema::dropIfExists('analytics_cohorts');
        Schema::dropIfExists('analytics_events');
    }
};
