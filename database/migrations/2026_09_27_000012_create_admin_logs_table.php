<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action'); // create, update, delete, approve, reject
            $table->string('model_type'); // User, Game, Achievement, etc.
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('admin_id');
            $table->index('model_type');
            $table->index('action');
            $table->index('created_at');
        });

        Schema::create('admin_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->ipAddress('ip_address');
            $table->string('user_agent')->nullable();
            $table->timestamp('logged_in_at');
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamps();

            $table->index('admin_id');
            $table->index('logged_in_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_access_logs');
        Schema::dropIfExists('admin_logs');
    }
};
