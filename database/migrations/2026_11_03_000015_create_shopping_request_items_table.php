<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_request_items', function (Blueprint $table) {
            $table->id('shopping_request_item_id');
            $table->foreignId('shopping_request_id')
                ->constrained('shopping_requests', 'shopping_request_id')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->unsignedInteger('weight_grams')->default(0);
            $table->unsignedInteger('total_weight_grams')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_request_items');
    }
};
