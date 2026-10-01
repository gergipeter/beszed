<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| The free/premium plan (App\Beszed\Entitlements reads it). 2026_09_27_000013 adds this column too,
| but on a database where that migration is recorded as run without its changes applied the column
| is missing, so this adds just the plan when it is not there yet.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'subscription_plan')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('subscription_plan', ['free', 'premium', 'family'])->default('free');
        });
    }

    public function down(): void
    {
        // Left in place: 2026_09_27_000013 owns the column on databases where it ran properly.
    }
};
