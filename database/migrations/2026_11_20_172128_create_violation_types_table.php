<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violation_types', function (Blueprint $table) {
            $table->id('violation_type_id');
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            // Poin awal yang diusulkan, admin boleh menyesuaikan dalam rentang min-max.
            // Validasi min <= default <= max dilakukan di form, bukan di database.
            $table->unsignedSmallInteger('default_points');
            $table->unsignedSmallInteger('min_points');
            $table->unsignedSmallInteger('max_points');
            // Jenis yang sudah pernah dipakai tidak boleh dihapus, cukup dinonaktifkan.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_types');
    }
};