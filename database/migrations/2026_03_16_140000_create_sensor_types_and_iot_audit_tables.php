<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('category', 50)->default('general');
            $table->boolean('is_optional')->default(true);
            $table->json('telemetry_keys')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('container_sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sensor_type_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['container_id', 'sensor_type_id']);
        });

        Schema::create('iot_audit_chain', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 64)->index();
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('sequence');
            $table->string('prev_hash', 64)->nullable();
            $table->string('row_hash', 64)->index();
            $table->timestamps();

            $table->unique(['container_id', 'sequence']);
            $table->index(['container_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_audit_chain');
        Schema::dropIfExists('container_sensors');
        Schema::dropIfExists('sensor_types');
    }
};
