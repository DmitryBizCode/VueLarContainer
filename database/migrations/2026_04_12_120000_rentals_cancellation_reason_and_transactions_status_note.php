<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->after('rejection_reason');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->text('status_note')->nullable()->after('refund_reason');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('status_note');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('cancellation_reason');
        });
    }
};
