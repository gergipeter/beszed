<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            // Parental Controls (Phase 5)
            $table->integer('screen_time_limit_minutes')->nullable()->default(60);
            $table->enum('content_filter_level', ['kid', 'medium', 'teen'])->default('medium');
            $table->time('bedtime_start')->nullable()->default('21:00');
            $table->time('bedtime_end')->nullable()->default('07:00');
            $table->json('allowed_games')->nullable();

            // Encryption (Phase 5)
            $table->text('public_encryption_key')->nullable();
            $table->text('secret_encryption_key')->nullable(); // Encrypted by Laravel

            // Classroom (Phase 4)
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn([
                'screen_time_limit_minutes',
                'content_filter_level',
                'bedtime_start',
                'bedtime_end',
                'allowed_games',
                'public_encryption_key',
                'secret_encryption_key',
                'classroom_id',
            ]);
        });
    }
};
