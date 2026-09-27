<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Badges/Achievements
        Schema::create('beszed_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->string('badge');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->timestamp('earned_at');
            $table->timestamps();

            $table->unique(['child_id', 'badge']);
            $table->index('earned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beszed_badges');
    }
};
