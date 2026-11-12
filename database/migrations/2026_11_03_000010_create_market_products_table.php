<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_products', function (Blueprint $table) {
            $table->id('market_product_id');
            $table->foreignId('market_id')
                ->constrained('markets', 'market_id')->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products', 'product_id')->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->decimal('stock', 10, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['market_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_products');
    }
};
