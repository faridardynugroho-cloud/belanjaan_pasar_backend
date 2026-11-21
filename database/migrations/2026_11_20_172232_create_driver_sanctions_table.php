<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_sanctions', function (Blueprint $table) {
            $table->id('driver_sanction_id');
            $table->foreignId('driver_profile_id')
                ->constrained('driver_profiles', 'driver_profile_id')->restrictOnDelete();
            $table->enum('type', ['temporary', 'permanent', 'termination']);
            // Dalam menit. Kosong untuk permanent dan termination.
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('reason');
            $table->text('notes')->nullable();
            // Null = dijatuhkan oleh sistem. restrictOnDelete supaya admin yang dihapus
            // tidak terbaca sebagai "Sistem".
            $table->foreignId('imposed_by')->nullable()
                ->constrained('users', 'user_id')->restrictOnDelete();
            // Laporan pemicu, hanya untuk sanksi otomatis dari poin.
            $table->foreignId('driver_violation_id')->nullable()
                ->constrained('driver_violations', 'driver_violation_id')->restrictOnDelete();
            // Snapshot total poin saat sanksi dijatuhkan (poin bisa kedaluwarsa kemudian).
            $table->unsignedSmallInteger('points_at_imposition')->nullable();

            // started_at null = menunggu pesanan driver selesai. Diisi dengan waktu
            // selesai pesanan terakhir, expires_at = started_at + duration_minutes.
            $table->dateTime('started_at')->nullable();
            $table->dateTime('expires_at')->nullable();

            // Pencabutan manual atau penggantian oleh sanksi baru.
            $table->dateTime('lifted_at')->nullable();
            $table->enum('lift_reason', ['manual', 'replaced'])->nullable();
            // Null kalau diganti otomatis oleh sistem.
            $table->foreignId('lifted_by')->nullable()
                ->constrained('users', 'user_id')->restrictOnDelete();
            $table->text('lift_note')->nullable();
            $table->foreignId('replaced_by')->nullable()
                ->constrained('driver_sanctions', 'driver_sanction_id')->nullOnDelete();
            $table->timestamps();

            // Cek sanksi aktif milik satu driver, dan filter "akan berakhir".
            $table->index(['driver_profile_id', 'lifted_at', 'expires_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_sanctions');
    }
};