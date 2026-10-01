<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beszed_attempts', function (Blueprint $t) {
            // "stars" = count of a child's correct answers, asked on every attempt and session start.
            $t->index(['child_id', 'correct'], 'beszed_attempts_child_correct_index');
        });
    }

    public function down(): void
    {
        Schema::table('beszed_attempts', fn (Blueprint $t) => $t->dropIndex('beszed_attempts_child_correct_index'));
    }
};
