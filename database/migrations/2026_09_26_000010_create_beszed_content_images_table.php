<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beszed_content_images', function (Blueprint $t) {
            $t->id();
            $t->string('disk', 20);
            $t->string('path', 255);
            $t->string('mime', 100);
            $t->unsignedInteger('size');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beszed_content_images');
    }
};
