<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->foreignId('container_id')->constrained()->onDelete('restrict');
            $table->unsignedBigInteger('route_id')->nullable()->index();
            $table->foreignId('origin_port_id')->nullable()->constrained('ports')->nullOnDelete();
            $table->foreignId('destination_port_id')->nullable()->constrained('ports')->nullOnDelete();
            $table->timestamp('start_date')->useCurrent();
            $table->timestamp('end_date')->nullable();
            $table->timestamp('actual_return_date')->nullable();
            $table->unsignedInteger('rental_days')->default(1);
            $table->json('cargo_types')->nullable();
            $table->text('cargo_details')->nullable();
            $table->decimal('requested_weight', 10, 2)->nullable();
            $table->decimal('cargo_volume_cbm', 10, 3)->nullable();
            $table->unsignedInteger('package_count')->nullable();
            $table->decimal('cargo_value', 12, 2)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('routing_priority', 20)->nullable();
            $table->string('incoterm', 10)->nullable();
            $table->string('loading_type', 20)->default('fcl');
            $table->string('delivery_mode', 30)->default('port_to_port');
            $table->string('sustainability_pref', 30)->default('standard');
            $table->boolean('insurance_required')->default(false);
            $table->boolean('requires_customs_clearance')->default(false);
            $table->boolean('hazardous_material')->default(false);
            $table->boolean('requires_escort')->default(false);
            $table->boolean('seal_required')->default(false);
            $table->string('un_number', 20)->nullable();
            $table->string('dangerous_goods_class', 20)->nullable();
            $table->string('origin_customs_code', 20)->nullable();
            $table->string('destination_customs_code', 20)->nullable();
            $table->decimal('temperature_min', 5, 2)->nullable();
            $table->decimal('temperature_max', 5, 2)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('pickup_address', 255)->nullable();
            $table->string('delivery_address', 255)->nullable();
            $table->timestamp('pickup_window_start')->nullable();
            $table->timestamp('pickup_window_end')->nullable();
            $table->timestamp('quote_expires_at')->nullable();
            $table->boolean('terms_accepted')->default(false);
            $table->text('special_requirements')->nullable();
            $table->decimal('estimated_distance', 12, 2)->nullable();
            $table->decimal('price', 15, 2)->default(0.00);
            $table->json('price_breakdown')->nullable();
            $table->string('status', 50)->default('pending_approval');
            $table->boolean('is_telemetry_active')->default(true);
            $table->string('payment_status', 50)->default('unpaid');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('payment_approved_at')->nullable();
            $table->foreignId('payment_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('contract_pdf')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['container_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
