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
        Schema::create('driver_reviews', function (Blueprint $table) {
            $table->id('driver_review_id');
            $table->foreignId('delivery_order_id')->unique()
                ->constrained('delivery_orders', 'delivery_order_id')->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignId('driver_profile_id')
                ->constrained('driver_profiles', 'driver_profile_id')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['driver_profile_id', 'is_visible']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_reviews');
    }
};
