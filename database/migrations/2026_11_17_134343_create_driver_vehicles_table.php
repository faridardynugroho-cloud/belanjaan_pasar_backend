<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_vehicles', function (Blueprint $table) {
            $table->id('driver_vehicle_id');
            $table->foreignId('driver_profile_id')->unique()
                ->constrained('driver_profiles', 'driver_profile_id')->cascadeOnDelete();
            $table->string('vehicle_type', 50);
            $table->string('vehicle_brand', 50);
            $table->string('vehicle_model', 50);
            $table->string('vehicle_plate', 20);
            $table->string('vehicle_color', 30);
            $table->unsignedSmallInteger('production_year');
            $table->string('vehicle_photo');
            $table->string('stnk_photo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_vehicles');
    }
};