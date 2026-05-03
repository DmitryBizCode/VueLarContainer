<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vessels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('imo_number', 20)->unique();
            $table->integer('capacity_teu');
            $table->string('status', 50)->default('active');
            $table->foreignId('current_port_id')->nullable()->constrained('ports')->nullOnDelete();
            $table->timestampTz('berth_busy_until')->nullable();
            $table->timestampTz('out_of_service_until')->nullable();
            $table->date('last_inspection_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vessels');
    }
};
