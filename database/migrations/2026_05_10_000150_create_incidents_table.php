<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('severity', 20);
            $table->text('description');
            $table->foreignId('container_id')->nullable()->constrained();
            $table->foreignId('shipment_id')->nullable()->constrained();
            $table->string('insurance_policy_number', 100)->nullable();
            $table->timestamp('reported_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_status', 50)->default('under_investigation');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
