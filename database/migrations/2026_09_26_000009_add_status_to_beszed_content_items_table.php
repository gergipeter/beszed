<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beszed_content_items', function (Blueprint $t) {
            $t->string('status', 10)->default('live'); // live, draft
        });
    }

    public function down(): void
    {
        Schema::table('beszed_content_items', function (Blueprint $t) {
            $t->dropColumn('status');
        });
    }
};
