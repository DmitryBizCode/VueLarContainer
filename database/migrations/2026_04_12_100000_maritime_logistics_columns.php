<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('city');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('vessels', function (Blueprint $table) {
            $table->timestampTz('berth_busy_until')->nullable()->after('current_port_id');
            $table->timestampTz('out_of_service_until')->nullable()->after('berth_busy_until');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedTinyInteger('leg_sequence')->default(1)->after('route_id');
            $table->timestampTz('port_operations_until')->nullable()->after('actual_arrival_date');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->string('routing_priority', 20)->nullable()->after('priority');
        });

        $portCoords = [
            'Port of Odesa' => [46.4858, 30.7395],
            'Port of Hamburg' => [53.5511, 9.9937],
            'Port of Gdansk' => [54.3520, 18.6466],
            'Port of Rotterdam' => [51.9244, 4.4777],
            'Port of Valencia' => [39.4461, -0.3199],
        ];
        foreach ($portCoords as $name => [$lat, $lng]) {
            DB::table('ports')->where('name', $name)->update([
                'latitude' => $lat,
                'longitude' => $lng,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('routing_priority');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['leg_sequence', 'port_operations_until']);
        });

        Schema::table('vessels', function (Blueprint $table) {
            $table->dropColumn(['berth_busy_until', 'out_of_service_until']);
        });

        Schema::table('ports', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
