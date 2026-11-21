<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shopping_request_reviews', function (Blueprint $table) {
            $table->id('shopping_request_review_id');
            $table->foreignId('order_id')->unique()
                ->constrained('orders', 'order_id')->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users', 'user_id')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopping_request_reviews');
    }
};
