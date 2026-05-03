<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_port_id')->constrained('ports');
            $table->foreignId('destination_port_id')->constrained('ports');
            $table->integer('estimated_days');
            $table->float('distance')->default(0.00);
            $table->json('sea_path')->nullable();
            $table->string('route_status', 50)->default('open');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
