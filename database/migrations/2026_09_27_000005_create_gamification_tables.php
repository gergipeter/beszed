<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The rewards migration (2026_09_26_000001) already creates beszed_badges (child, badge, earned_at).
        // The achievements also store a name, description and icon, so add those to it.
        if (Schema::hasTable('beszed_badges')) {
            Schema::table('beszed_badges', function (Blueprint $table) {
                if (! Schema::hasColumn('beszed_badges', 'name')) {
                    $table->string('name')->nullable();
                }
                if (! Schema::hasColumn('beszed_badges', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('beszed_badges', 'icon')) {
                    $table->string('icon')->nullable();
                }
                if (! Schema::hasColumn('beszed_badges', 'created_at')) {
                    $table->timestamps();
                }
            });

            return;
        }

        Schema::create('beszed_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->string('badge');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->timestamp('earned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['child_id', 'badge']);
            $table->index('earned_at');
        });
    }

    public function down(): void
    {
        // beszed_badges belongs to the rewards migration; only take back what this one added.
        Schema::table('beszed_badges', function (Blueprint $table) {
            foreach (['name', 'description', 'icon'] as $column) {
                if (Schema::hasColumn('beszed_badges', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
