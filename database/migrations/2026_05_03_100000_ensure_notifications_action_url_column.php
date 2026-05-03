<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent patch for databases where the 2026_05_02_120000 migration did not run
 * or failed before adding action_url (e.g. migrate not executed in Docker after deploy).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        if (! Schema::hasColumn('notifications', 'action_url')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('action_url', 500)->nullable();
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            try {
                DB::statement('ALTER TABLE notifications ALTER COLUMN type TYPE VARCHAR(50)');
            } catch (\Throwable) {
                // Column may already be VARCHAR(50) or unconstrained TEXT in some DBs.
            }
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            try {
                DB::statement('ALTER TABLE notifications MODIFY type VARCHAR(50) NOT NULL DEFAULT \'info\'');
            } catch (\Throwable) {
                //
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'action_url')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('action_url');
            });
        }
    }
};
