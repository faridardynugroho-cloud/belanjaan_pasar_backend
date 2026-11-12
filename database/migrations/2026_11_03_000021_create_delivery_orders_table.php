<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id('delivery_order_id');
            $table->foreignId('delivery_id')
                ->constrained('deliveries', 'delivery_id')->cascadeOnDelete();
            $table->foreignId('order_id')
                ->constrained('orders', 'order_id')->restrictOnDelete();
            $table->dateTime('assigned_at');
            $table->timestamps();

            $table->unique(['delivery_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};
