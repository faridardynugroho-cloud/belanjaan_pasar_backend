<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id('order_item_id');
            $table->foreignId('order_id')
                ->constrained('orders', 'order_id')->cascadeOnDelete();
            $table->foreignId('market_product_id')->nullable()
                ->constrained('market_products', 'market_product_id')->nullOnDelete();
            $table->foreignId('shopping_request_item_id')->nullable()
                ->constrained('shopping_request_items', 'shopping_request_item_id')->nullOnDelete();
            $table->string('product_name');
            $table->string('unit', 20);
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->unsignedInteger('weight_grams')->default(0);
            $table->unsignedInteger('total_weight_grams')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
