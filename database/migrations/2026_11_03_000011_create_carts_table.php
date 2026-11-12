<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id('cart_id');
            $table->foreignId('user_id')
                ->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignId('market_id')
                ->constrained('markets', 'market_id')->restrictOnDelete();
            $table->enum('status', ['active', 'checked_out', 'abandoned'])->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
