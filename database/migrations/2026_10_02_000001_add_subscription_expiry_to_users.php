<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Where a paid plan came from and when it ends. Entitlements stops treating the plan as premium once the end date has
| passed, even if the store's "expired" message never reached us. null = no end (the review account, local accounts).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('subscription_expires_at')->nullable();
            $table->string('subscription_source', 20)->nullable(); // app_store | play_store | stripe …, from RevenueCat
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['subscription_expires_at', 'subscription_source']);
        });
    }
};
