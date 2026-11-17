<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_requests', function (Blueprint $table) {
            $table->id('shopping_request_id');
            $table->foreignId('user_id')
                ->constrained('users', 'user_id')->restrictOnDelete();
            $table->foreignId('market_id')
                ->constrained('markets', 'market_id')->restrictOnDelete();
            $table->string('request_number', 50)->unique();
            $table->longText('original_list');
            $table->text('notes')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_phone', 20);
            $table->text('shipping_address');
            $table->decimal('shipping_latitude', 10, 7);
            $table->decimal('shipping_longitude', 10, 7);
            $table->decimal('service_fee', 12, 2)->default(10000);
            $table->decimal('total', 12, 2)->default(0);
            $table->unsignedInteger('total_weight_grams')->default(0);
            $table->enum('status', [
                'pending', 'assigned', 'shopping', 'price_sent', 'completed', 'cancelled',
            ])->default('pending');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_requests');
    }
};
