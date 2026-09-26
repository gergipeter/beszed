<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beszed_content_item_edits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('content_item_id')->nullable()->constrained('beszed_content_items')->nullOnDelete();
            $t->string('editor_email', 255);
            $t->string('action', 20); // created, updated, deactivated, deleted, restored
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['content_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beszed_content_item_edits');
    }
};
