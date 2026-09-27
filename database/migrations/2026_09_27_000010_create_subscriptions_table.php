<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('stripe_id')->unique()->nullable();
            $table->string('stripe_status')->default('incomplete');
            $table->string('stripe_price_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index('stripe_id');
            $table->index('user_id');
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->string('stripe_id')->unique();
            $table->string('stripe_product_id');
            $table->string('stripe_price_id');
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('stripe_id')->unique();
            $table->string('stripe_hosted_invoice_url')->nullable();
            $table->string('stripe_invoice_pdf')->nullable();
            $table->integer('amount');
            $table->string('currency')->default('eur');
            $table->string('status')->default('open');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('stripe_id');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('stripe_id')->unique();
            $table->integer('amount');
            $table->string('currency')->default('eur');
            $table->string('status')->default('processing');
            $table->string('payment_method')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('stripe_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
    }
};
