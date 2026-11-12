<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_request_assignments', function (Blueprint $table) {
            $table->id('shopping_request_assignment_id');
            $table->foreignId('shopping_request_id')
                ->constrained('shopping_requests', 'shopping_request_id')->cascadeOnDelete();
            $table->foreignId('helper_id')
                ->constrained('helpers', 'helper_id')->restrictOnDelete();
            $table->foreignId('assigned_by')
                ->constrained('users', 'user_id')->restrictOnDelete();
            $table->dateTime('assigned_at');
            $table->enum('status', ['assigned', 'in_progress', 'completed', 'cancelled'])
                ->default('assigned');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_request_assignments');
    }
};
