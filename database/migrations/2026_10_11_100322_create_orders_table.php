<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_token', 64)->nullable()->unique();
            $table->string('status', 20)->index();
            $table->string('payment_method', 30)->default('cash_on_delivery');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 50)->nullable();
            $table->text('customer_address')->nullable();
            $table->string('delivery_name');
            $table->string('delivery_email');
            $table->string('delivery_phone', 50)->nullable();
            $table->text('delivery_address');
            $table->text('notes')->nullable();
            $table->timestamp('placed_at')->nullable()->index();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();
            $table->index(['status', 'placed_at']);
            $table->index('customer_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
