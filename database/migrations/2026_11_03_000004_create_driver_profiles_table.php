<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id('driver_profile_id');
            $table->foreignId('user_id')->unique()
                ->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('vehicle_type', 50)->nullable();
            $table->string('vehicle_plate', 20)->nullable();
            $table->boolean('is_available')->default(true);
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');
    }
};
