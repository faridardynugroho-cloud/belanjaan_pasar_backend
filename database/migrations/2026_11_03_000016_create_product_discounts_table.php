<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_discounts', function (Blueprint $table) {
            $table->id('product_discount_id');
            $table->foreignId('market_product_id')
                ->constrained('market_products', 'market_product_id')->cascadeOnDelete();
            $table->enum('type', ['percent', 'fixed']);
            $table->decimal('value', 12, 2);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['market_product_id', 'is_active', 'starts_at', 'ends_at'], 'product_discounts_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_discounts');
    }
};