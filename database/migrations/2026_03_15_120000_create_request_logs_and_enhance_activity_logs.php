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
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 100)->nullable()->index();
            $table->string('path', 500);
            $table->string('method', 10);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('timezone', 80)->nullable();
            $table->smallInteger('gmt_offset_minutes')->nullable();
            $table->string('browser', 80)->nullable();
            $table->string('browser_version', 20)->nullable();
            $table->string('device_type', 40)->nullable();
            $table->string('platform', 80)->nullable();
            $table->string('accept_language', 255)->nullable();
            $table->text('referer')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('request_path', 500)->nullable()->after('description');
            $table->string('country_code', 10)->nullable()->after('request_path');
            $table->string('timezone', 80)->nullable()->after('country_code');
            $table->smallInteger('gmt_offset_minutes')->nullable()->after('timezone');
            $table->string('browser', 80)->nullable()->after('gmt_offset_minutes');
            $table->string('device_type', 40)->nullable()->after('browser');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_logs');
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn([
                'request_path',
                'country_code',
                'timezone',
                'gmt_offset_minutes',
                'browser',
                'device_type',
            ]);
        });
    }
};
