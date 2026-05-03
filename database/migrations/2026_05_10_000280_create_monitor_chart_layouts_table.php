<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_chart_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->boolean('is_default')->default(false);
            $table->json('config');
            $table->timestamps();

            $table->index(['user_id', 'rental_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_chart_layouts');
    }
};
