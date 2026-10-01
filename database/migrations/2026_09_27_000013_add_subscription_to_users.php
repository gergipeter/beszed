<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'subscription_plan')) {
                $table->enum('subscription_plan', ['free', 'premium', 'family'])->default('free')->after('language');
            }
            $table->boolean('is_admin')->default(false)->after('subscription_plan');
            $table->integer('games_played_this_week')->default(0)->after('is_admin');
            $table->integer('games_limit_weekly')->default(5)->after('games_played_this_week');
            $table->text('preferences')->nullable()->after('games_limit_weekly');
            $table->timestamp('last_activity_at')->nullable()->after('preferences');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_plan',
                'is_admin',
                'games_played_this_week',
                'games_limit_weekly',
                'preferences',
                'last_activity_at',
            ]);
        });
    }
};
