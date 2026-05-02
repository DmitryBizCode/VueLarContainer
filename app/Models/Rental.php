<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rental extends Model
{
    /**
     * Model defaults. `is_telemetry_active`: when false, simulation worker/schedule skips this rental (no auto metrics); monitor and manual actuators still work.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_telemetry_active' => true,
    ];

    /** Statuses that allow simulation ticks and actuator writes (active lifecycle only). */
    public const IOT_ELIGIBLE_STATUSES = ['approved', 'scheduled', 'in_progress', 'active'];

    /** Statuses that may open the IoT monitor and read chart/telemetry APIs (includes completed for history). */
    public const IOT_MONITOR_ACCESS_STATUSES = ['approved', 'scheduled', 'in_progress', 'active', 'completed'];

    protected $fillable = [
        'user_id',
        'container_id',
        'route_id',
        'origin_port_id',
        'destination_port_id',
        'start_date',
        'end_date',
        'actual_return_date',
        'rental_days',
        'cargo_types',
        'cargo_details',
        'requested_weight',
        'cargo_volume_cbm',
        'package_count',
        'cargo_value',
        'priority',
        'routing_priority',
        'incoterm',
        'loading_type',
        'delivery_mode',
        'sustainability_pref',
        'insurance_required',
        'requires_customs_clearance',
        'hazardous_material',
        'requires_escort',
        'seal_required',
        'un_number',
        'dangerous_goods_class',
        'origin_customs_code',
        'destination_customs_code',
        'temperature_min',
        'temperature_max',
        'contact_name',
        'contact_phone',
        'pickup_address',
        'delivery_address',
        'pickup_window_start',
        'pickup_window_end',
        'quote_expires_at',
        'terms_accepted',
        'special_requirements',
        'estimated_distance',
        'price',
        'price_breakdown',
        'status',
        'is_telemetry_active',
        'payment_status',
        'reviewed_by',
        'reviewed_at',
        'payment_approved_at',
        'payment_approved_by',
        'rejection_reason',
        'cancellation_reason',
        'contract_pdf',
        'description',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'actual_return_date' => 'datetime',
        'rental_days' => 'integer',
        'cargo_types' => 'array',
        'requested_weight' => 'decimal:2',
        'cargo_volume_cbm' => 'decimal:3',
        'package_count' => 'integer',
        'cargo_value' => 'decimal:2',
        'insurance_required' => 'boolean',
        'requires_customs_clearance' => 'boolean',
        'hazardous_material' => 'boolean',
        'requires_escort' => 'boolean',
        'seal_required' => 'boolean',
        'temperature_min' => 'decimal:2',
        'temperature_max' => 'decimal:2',
        'pickup_window_start' => 'datetime',
        'pickup_window_end' => 'datetime',
        'quote_expires_at' => 'datetime',
        'terms_accepted' => 'boolean',
        'estimated_distance' => 'decimal:2',
        'price' => 'decimal:2',
        'price_breakdown' => 'array',
        'reviewed_at' => 'datetime',
        'payment_approved_at' => 'datetime',
        'is_telemetry_active' => 'boolean',
    ];

    public function isIotEligible(): bool
    {
        return in_array((string) $this->status, self::IOT_ELIGIBLE_STATUSES, true);
    }

    public function canAccessIotMonitor(): bool
    {
        if (! in_array((string) $this->status, self::IOT_MONITOR_ACCESS_STATUSES, true)) {
            return false;
        }

        // Completed rentals retain read-only history access after end_date.
        if ((string) $this->status === 'completed') {
            return true;
        }

        return $this->end_date === null || ! $this->end_date->isPast();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
