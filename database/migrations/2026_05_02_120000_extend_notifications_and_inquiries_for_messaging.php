<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // No ->after(): PostgreSQL/SQLite ignore column order; avoids builder quirks.
            $table->string('action_url', 500)->nullable();
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN type TYPE VARCHAR(50)');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE notifications MODIFY type VARCHAR(50) NOT NULL DEFAULT \'info\'');
        }

        Schema::table('inquiries', function (Blueprint $table) {
            $table->foreignId('submitted_by_user_id')->nullable()->after('converted_user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by_user_id');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('action_url');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN type TYPE VARCHAR(20)');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE notifications MODIFY type VARCHAR(20) NOT NULL DEFAULT \'info\'');
        }
    }
};
