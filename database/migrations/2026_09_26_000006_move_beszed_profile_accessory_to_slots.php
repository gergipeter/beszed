<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Databases created before the wardrobe got slots have a single `accessory`
 * column (new ones get `accessories` + `scene` from the rewards migration).
 * Adds the new columns, keeps what the child was wearing, drops the old one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beszed_profiles', function (Blueprint $t) {
            if (! Schema::hasColumn('beszed_profiles', 'accessories')) {
                $t->json('accessories')->nullable();
            }
            if (! Schema::hasColumn('beszed_profiles', 'scene')) {
                $t->json('scene')->nullable();
            }
        });

        if (! Schema::hasColumn('beszed_profiles', 'accessory')) {
            return;
        }

        foreach (DB::table('beszed_profiles')->whereNotNull('accessory')->get(['id', 'accessory']) as $row) {
            $slot = config("beszed.rewards.accessories.{$row->accessory}.slot");
            if ($slot) {
                DB::table('beszed_profiles')->where('id', $row->id)->update(['accessories' => json_encode([$slot => $row->accessory])]);
            }
        }

        Schema::table('beszed_profiles', fn (Blueprint $t) => $t->dropColumn('accessory'));
    }

    public function down(): void
    {
        // Nothing to undo: the rewards migration defines the slot columns.
    }
};
