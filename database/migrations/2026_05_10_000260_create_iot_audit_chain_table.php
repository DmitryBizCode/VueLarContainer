<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }
};
