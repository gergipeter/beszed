<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compliance & Audit Logging
        Schema::create('compliance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action'); // read, write, delete, export, modify, consent
            $table->string('data_type'); // speech, medical, personal, encryption_keys
            $table->text('reason')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('timestamp');
            $table->timestamps();

            $table->index('child_id');
            $table->index('user_id');
            $table->index('action');
            $table->index('timestamp');
        });

        // GDPR Deletion Requests
        Schema::create('deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('requested_at');
            $table->timestamp('scheduled_for');
            $table->enum('status', ['pending', 'cancelled', 'completed'])->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_for');
        });

        // Encryption Keys (encrypted at rest)
        Schema::create('encryption_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->text('public_key');
            $table->text('secret_key'); // Encrypted by Laravel
            $table->timestamp('generated_at');
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();

            $table->unique('child_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_logs');
        Schema::dropIfExists('deletion_requests');
        Schema::dropIfExists('encryption_keys');
    }
};
