<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_usages', function (Blueprint $table) {
            $table->id('voucher_usage_id');
            $table->foreignId('voucher_id')
                ->constrained('vouchers', 'voucher_id')->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users', 'user_id')->restrictOnDelete();
            $table->foreignId('order_id')->unique()
                ->constrained('orders', 'order_id')->restrictOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->enum('status', ['applied', 'released'])->default('applied');
            $table->timestamps();

            $table->index(['voucher_id', 'status']);
            $table->index(['voucher_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_usages');
    }
};