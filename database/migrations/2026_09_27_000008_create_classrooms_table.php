<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // For invite links
            $table->text('description')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('school_name')->nullable();
            $table->string('grade_level')->nullable();
            $table->timestamps();

            $table->index('teacher_id');
            $table->index('code');
        });

        // Needs the classrooms table, so it lives here and not with the other parental controls (000007).
        if (! Schema::hasColumn('children', 'classroom_id')) {
            Schema::table('children', function (Blueprint $table) {
                $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('children', 'classroom_id')) {
            Schema::table('children', function (Blueprint $table) {
                $table->dropConstrainedForeignId('classroom_id');
            });
        }

        Schema::dropIfExists('classrooms');
    }
};
