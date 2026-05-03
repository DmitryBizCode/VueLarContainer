<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->string('currency', 10)->default('USD');
            $table->string('status', 50)->default('pending');
            $table->string('external_provider_id', 100)->nullable();
            $table->text('refund_reason')->nullable();
            $table->text('status_note')->nullable();
            $table->timestamp('transaction_date')->useCurrent();
            $table->string('payment_method', 50)->default('card');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
