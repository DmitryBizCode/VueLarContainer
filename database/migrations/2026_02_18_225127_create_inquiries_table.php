<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone_number',20)->nullable();
            $table->string('telegram_username',100)->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->enum('source', ['website', 'telegram_bot','etc'])->default('website');
            $table->enum('status', ['new', 'in_progress', 'contacted', 'converted', 'spam', 'closed'])->default('new');
            $table->foreignId('converted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
