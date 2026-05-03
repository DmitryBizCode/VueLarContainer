<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('container_simulation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete();
            $table->json('sensor_state');
            $table->json('actuators')->nullable();
            $table->timestampTz('last_tick_at')->nullable();
            $table->timestamps();

            $table->unique('container_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_simulation_snapshots');
    }
};
