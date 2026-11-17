<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_documents', function (Blueprint $table) {
            $table->id('driver_document_id');
            $table->foreignId('driver_profile_id')->unique()
                ->constrained('driver_profiles', 'driver_profile_id')->cascadeOnDelete();
            $table->string('nik', 16)->unique();
            $table->string('ktp_photo');
            $table->string('selfie_photo');
            $table->string('sim_number', 20);
            $table->string('sim_photo');
            $table->date('sim_expired_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_documents');
    }
};