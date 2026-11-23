<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id('order_id');
            $table->foreignId('user_id')
                ->constrained('users', 'user_id')->restrictOnDelete();
            $table->foreignId('market_id')
                ->constrained('markets', 'market_id')->restrictOnDelete();
            $table->foreignId('shopping_request_id')->nullable()->unique()
                ->constrained('shopping_requests', 'shopping_request_id')->nullOnDelete();
            $table->foreignId('voucher_id')->nullable()
                ->constrained('vouchers', 'voucher_id')->nullOnDelete();
            $table->string('order_number', 50)->unique();
            $table->string('voucher_code', 50)->nullable();
            // snapshot alamat saat checkout
            $table->string('recipient_name');
            $table->string('recipient_phone', 20);
            $table->text('shipping_address');
            $table->decimal('shipping_latitude', 10, 7);
            $table->decimal('shipping_longitude', 10, 7);

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('voucher_discount', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('delivery_discount', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->unsignedInteger('total_weight_grams')->default(0);
            $table->enum('status', [
                'pending_payment',
                'paid',
                'processing',
                'ready_for_delivery',
                'on_delivery',
                'completed',
                'cancelled',
            ])->default('pending_payment');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
