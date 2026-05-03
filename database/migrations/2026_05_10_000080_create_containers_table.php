<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number', 50)->unique();
            $table->string('type', 50)->default('standard');
            $table->decimal('width', 8, 2);
            $table->decimal('length', 8, 2);
            $table->decimal('height', 8, 2);
            $table->decimal('max_weight', 10, 2);
            $table->date('manufacture_date')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('iot_active')->default(false);
            $table->string('current_status', 50)->default('available');
            $table->foreignId('owner_id')->constrained();
            $table->foreignId('current_port_id')->nullable()->constrained('ports');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
