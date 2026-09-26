<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Read-only report links a parent gives the speech therapist. Only the
        // token's hash is stored; the link itself is shown once, when created.
        Schema::create('beszed_shares', function (Blueprint $t) {
            $t->id();
            $t->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $t->char('token_hash', 64)->unique();
            $t->string('label', 80)->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('last_viewed_at')->nullable();
            $t->unsignedInteger('views')->default(0);
            $t->timestamps();
        });

        // Csillám's suggested games for one day (the child's local day).
        Schema::create('beszed_daily_paths', function (Blueprint $t) {
            $t->id();
            $t->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $t->date('day');
            $t->json('games');
            $t->json('done');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['child_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beszed_daily_paths');
        Schema::dropIfExists('beszed_shares');
    }
};
