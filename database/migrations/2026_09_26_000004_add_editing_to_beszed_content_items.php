<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beszed_content_items', function (Blueprint $t) {
            // seed = from database/seeders/data/beszed/*.json, admin = made in the content editor
            $t->string('source', 10)->default('seed');
            // sha1 of the JSON payload, so re-seeding updates instead of re-creating
            $t->string('seed_key', 40)->nullable();
            // set when changed in the editor: the seeder then leaves the item alone
            $t->timestamp('edited_at')->nullable();
            $t->index(['game', 'seed_key']);
        });

        // Existing rows all came from the seeder: give them their key.
        DB::table('beszed_content_items')->orderBy('id')->each(function ($row) {
            DB::table('beszed_content_items')->where('id', $row->id)
                ->update(['seed_key' => sha1(json_encode(json_decode($row->payload, true)))]);
        });
    }

    public function down(): void
    {
        Schema::table('beszed_content_items', function (Blueprint $t) {
            $t->dropIndex(['game', 'seed_key']);
            $t->dropColumn(['source', 'seed_key', 'edited_at']);
        });
    }
};
