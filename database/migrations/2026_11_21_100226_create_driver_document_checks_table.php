<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hasil pemeriksaan admin per dokumen saat verifikasi pendaftaran driver.
        // Satu baris per dokumen per driver (ktp, selfie, sim, foto kendaraan, stnk).
        Schema::create('driver_document_checks', function (Blueprint $table) {
            $table->id('driver_document_check_id');
            $table->foreignId('driver_profile_id')
                ->constrained('driver_profiles', 'driver_profile_id')->cascadeOnDelete();
            $table->enum('item', ['ktp', 'selfie', 'sim', 'vehicle', 'stnk']);
            $table->enum('status', ['pending', 'valid', 'invalid'])->default('pending');
            $table->text('note')->nullable();          // alasan kalau tidak valid (instruksi perbaikan untuk driver)
            $table->foreignId('checked_by')->nullable()
                ->constrained('users', 'user_id')->nullOnDelete();
            $table->dateTime('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['driver_profile_id', 'item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_document_checks');
    }
};