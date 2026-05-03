<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notification_email_enabled')->default(true)->after('telegram_link_code_expires_at');
            $table->boolean('notification_telegram_enabled')->default(true)->after('notification_email_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notification_email_enabled', 'notification_telegram_enabled']);
        });
    }
};
