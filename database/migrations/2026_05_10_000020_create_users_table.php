<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('company_name', 100)->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('phone_number', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('photo')->nullable();
            $table->string('account_status', 50)->default('pending_verification');
            $table->string('role', 50)->default('client');
            $table->decimal('commission_rate', 8, 4)->nullable();
            $table->string('bonus_type', 20)->nullable();
            $table->decimal('bonus_value', 12, 2)->nullable();
            $table->boolean('notification_email_enabled')->default(true);
            $table->boolean('notification_telegram_enabled')->default(true);
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
