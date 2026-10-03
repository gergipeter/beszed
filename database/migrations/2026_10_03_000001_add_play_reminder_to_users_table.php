<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A gentle evening e-mail ("you haven't played today") for a parent who asked for it: off until the parent turns it on. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('play_reminder_enabled')->default(false)->after('weekly_report_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('play_reminder_enabled');
        });
    }
};
