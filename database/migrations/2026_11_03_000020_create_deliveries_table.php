<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id('delivery_id');
            $table->foreignId('driver_profile_id')
                ->constrained('driver_profiles', 'driver_profile_id')
                ->restrictOnDelete();
            $table->string('delivery_number', 50)->unique();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index([
                'driver_profile_id',
                'completed_at',
                'cancelled_at'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
