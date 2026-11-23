<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_violations', function (Blueprint $table) {
            $table->id('driver_violation_id');
            $table->foreignId('driver_profile_id')
                ->constrained('driver_profiles', 'driver_profile_id')->restrictOnDelete();
            // Wajib untuk laporan customer, kosong kalau admin menginput manual.
            $table->foreignId('delivery_order_id')->nullable()
                ->constrained('delivery_orders', 'delivery_order_id')->restrictOnDelete();
            $table->foreignId('violation_type_id')
                ->constrained('violation_types', 'violation_type_id')->restrictOnDelete();
            $table->enum('source', ['customer', 'admin']);
            $table->foreignId('reported_by')
                ->constrained('users', 'user_id')->restrictOnDelete();
            $table->text('description');
            $table->string('evidence_photo')->nullable();
            $table->enum('status', ['reported', 'validated', 'rejected', 'revoked'])
                ->default('reported');

            // Snapshot poin, diisi saat divalidasi (bukan dibaca ulang dari violation_types).
            $table->unsignedSmallInteger('points')->nullable();
            $table->foreignId('handled_by')->nullable()
                ->constrained('users', 'user_id')->restrictOnDelete();
            // Sekaligus waktu poin diberikan.
            $table->dateTime('handled_at')->nullable();
            // Wajib diisi kalau laporan ditolak (validasi di form).
            $table->text('handle_note')->nullable();
            // handled_at + 1 bulan, hanya untuk laporan berstatus validated.
            $table->dateTime('points_expires_at')->nullable();

            // Kalau admin membatalkan validasi yang sudah terlanjur diberikan.
            $table->foreignId('revoked_by')->nullable()
                ->constrained('users', 'user_id')->restrictOnDelete();
            $table->dateTime('revoked_at')->nullable();
            $table->text('revoke_note')->nullable();
            $table->timestamps();

            // Hitung total poin aktif: status = validated dan points_expires_at > now().
            // Nama index dipendekkan karena nama otomatis melewati batas 64 karakter MySQL.
            $table->index(['driver_profile_id', 'status', 'points_expires_at'], 'dv_driver_status_expires_index');
            // Mencegah laporan ganda untuk hal yang sama pada pengantaran yang sama.
            $table->unique(['delivery_order_id', 'reported_by', 'violation_type_id'], 'dv_order_reporter_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_violations');
    }
};