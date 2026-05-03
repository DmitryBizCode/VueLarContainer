<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_route_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('segment_order')->default(1);
            $table->foreignId('from_port_id')->constrained('ports');
            $table->foreignId('to_port_id')->constrained('ports');
            $table->foreignId('route_id')->nullable()->constrained('routes')->nullOnDelete();
            $table->foreignId('vessel_id')->nullable()->constrained('vessels')->nullOnDelete();
            $table->timestampTz('planned_departure_at')->nullable();
            $table->timestampTz('planned_arrival_at')->nullable();
            $table->unsignedSmallInteger('travel_duration_hours')->default(0);
            $table->unsignedSmallInteger('waiting_time_before_hours')->default(0);
            $table->unsignedSmallInteger('waiting_time_after_hours')->default(0);
            $table->string('status', 30)->default('planned');
            $table->timestamps();
            $table->index(['rental_id', 'segment_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_route_segments');
    }
};
