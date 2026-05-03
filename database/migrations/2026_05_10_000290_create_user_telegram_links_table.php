<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_telegram_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_chat_id');
            $table->unsignedBigInteger('telegram_user_id')->nullable();
            $table->string('telegram_username', 255)->nullable();
            $table->string('first_name', 255)->nullable();
            $table->string('last_name', 255)->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamp('linked_at')->useCurrent();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error', 512)->nullable();
            $table->timestamps();

            $table->unique('telegram_chat_id');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_telegram_links');
    }
};
