<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('handling_status', 40)->default('new');
            $table->text('admin_notes')->nullable();
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('UPDATE inquiries SET handling_status = status::text');
        } else {
            DB::statement('UPDATE inquiries SET handling_status = status');
        }
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropColumn(['handling_status', 'admin_notes']);
        });
    }
};
