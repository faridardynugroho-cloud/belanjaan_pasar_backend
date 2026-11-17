<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->foreignId('order_id')
                ->constrained('orders', 'order_id')->restrictOnDelete();
            $table->string('payment_number', 50)->unique();
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_gateway', 50)->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'cancelled'])
                ->default('pending');
            $table->string('transaction_id')->nullable()->index();
            $table->text('payment_url')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
