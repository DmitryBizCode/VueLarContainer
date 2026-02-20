<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained();
            $table->foreignId('route_id')->constrained();
            $table->timestamp('departure_date');
            $table->timestamp('arrival_date');
            $table->timestamp('actual_departure_date')->nullable();
            $table->timestamp('actual_arrival_date')->nullable();
            $table->string('tracking_number',50)->unique();
            $table->string('status',50)->default('scheduled');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
