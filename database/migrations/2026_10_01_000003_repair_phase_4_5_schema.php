<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
| Some databases have the 2026_09_27_* migrations recorded as run without their tables and columns
| (the batch failed on MySQL once, and was marked done to get past it). Everything built on them —
| speech analysis, compliance, classrooms, subscriptions, analytics, admin logs — then fails at runtime.
|
| This runs each of those migrations again, in dependency order, but only when what it creates is
| missing. On a database that migrated properly (or a fresh one) every check passes and it does nothing.
*/
return new class extends Migration
{
    public function up(): void
    {
        $steps = [
            // file => "is this migration's work missing?"
            '2026_09_27_000004_create_speech_analysis_tables' => fn () => ! Schema::hasTable('beszed_speech_recordings')
                || ! Schema::hasTable('beszed_phoneme_progress')
                || ! Schema::hasTable('beszed_content_difficulty')
                || ! Schema::hasTable('beszed_progress_predictions')
                || ! Schema::hasTable('beszed_game_recommendations'),
            '2026_09_27_000005_create_gamification_tables' => fn () => ! Schema::hasColumn('beszed_badges', 'name'),
            '2026_09_27_000006_create_compliance_tables' => fn () => ! Schema::hasTable('compliance_logs'),
            // classrooms before the parental controls: 000008 also adds children.classroom_id
            '2026_09_27_000008_create_classrooms_table' => fn () => ! Schema::hasTable('classrooms'),
            '2026_09_27_000007_add_parental_controls_to_children' => fn () => ! Schema::hasColumn('children', 'screen_time_limit_minutes'),
            '2026_09_27_000009_add_language_to_users' => fn () => ! Schema::hasColumn('users', 'language'),
            '2026_09_27_000010_create_subscriptions_table' => fn () => ! Schema::hasTable('subscriptions'),
            '2026_09_27_000011_create_analytics_events_table' => fn () => ! Schema::hasTable('analytics_events'),
            '2026_09_27_000012_create_admin_logs_table' => fn () => ! Schema::hasTable('admin_logs'),
            '2026_09_27_000013_add_subscription_to_users' => fn () => ! Schema::hasColumn('users', 'is_admin'),
        ];

        foreach ($steps as $file => $missing) {
            if ($missing()) {
                (require __DIR__."/$file.php")->up();
            }
        }
    }

    public function down(): void
    {
        // Nothing to take back: it only completes migrations that are rolled back on their own.
    }
};
