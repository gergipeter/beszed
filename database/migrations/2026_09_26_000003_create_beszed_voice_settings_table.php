<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beszed_voice_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('voice', 32)->nullable();
            $t->string('rate', 8)->nullable();
            $t->string('pitch', 8)->nullable();
            $t->boolean('prefer_server_tts')->default(true);
            $t->boolean('muted')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beszed_voice_settings');
    }
};
