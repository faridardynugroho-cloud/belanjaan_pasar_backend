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
            $table->enum('status', ['pending', 'accepted', 'rejected', 'on_the_way', 'completed'])
                ->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->dateTime('assigned_at');
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['delivery_id', 'order_id']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};
