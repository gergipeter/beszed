<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('google_id')->nullable()->unique()->after('email');
            $t->string('avatar')->nullable()->after('google_id');
            // Google accounts have no local password.
            $t->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['google_id']);
            $t->dropColumn(['google_id', 'avatar']);
        });
    }
};
