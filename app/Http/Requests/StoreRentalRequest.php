<?php

namespace App\Http\Requests;

use App\Models\Route as ShippingRoute;
use App\Services\ContainerAvailabilityService;
use App\Services\RoutePathfinderService;
use App\Services\VesselPortScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_mode' => ['required', Rule::in(['route', 'ports'])],
            'route_id' => ['nullable', 'integer', 'exists:routes,id', 'required_if:route_mode,route'],
            'origin_port_id' => ['nullable', 'integer', 'exists:ports,id', 'required_if:route_mode,ports'],
            'destination_port_id' => ['nullable', 'integer', 'exists:ports,id', 'required_if:route_mode,ports', 'different:origin_port_id'],
            'container_id' => ['required', 'integer', 'exists:containers,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'cargo_types' => ['required', 'array', 'min:1'],
            'cargo_types.*' => ['string', Rule::in(['electronics', 'furniture', 'clothing', 'food', 'machinery', 'other'])],
            'cargo_details' => ['nullable', 'string', 'max:5000'],
            'requested_weight' => ['nullable', 'numeric', 'min:0'],
            'cargo_volume_cbm' => ['nullable', 'numeric', 'min:0'],
            'package_count' => ['nullable', 'integer', 'min:1'],
            'cargo_value' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['required', Rule::in(['normal', 'urgent', 'express'])],
            'routing_priority' => ['nullable', 'string', Rule::in(['speed', 'cost', 'balanced'])],
            'incoterm' => ['nullable', Rule::in(['EXW', 'FCA', 'FOB', 'CFR', 'CIF', 'DAP', 'DDP'])],
            'loading_type' => ['required', Rule::in(['fcl', 'lcl'])],
            'delivery_mode' => ['required', Rule::in(['port_to_port', 'door_to_port', 'port_to_door', 'door_to_door'])],
            'sustainability_pref' => ['required', Rule::in(['standard', 'eco_optimized', 'low_emission'])],
            'insurance_required' => ['required', 'boolean'],
            'requires_customs_clearance' => ['required', 'boolean'],
            'hazardous_material' => ['required', 'boolean'],
            'requires_escort' => ['required', 'boolean'],
            'seal_required' => ['required', 'boolean'],
            'un_number' => ['nullable', 'string', 'max:20', 'required_if:hazardous_material,1'],
            'dangerous_goods_class' => ['nullable', 'string', 'max:20', 'required_if:hazardous_material,1'],
            'origin_customs_code' => ['nullable', 'string', 'max:20', 'required_if:requires_customs_clearance,1'],
            'destination_customs_code' => ['nullable', 'string', 'max:20', 'required_if:requires_customs_clearance,1'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'pickup_window_start' => ['nullable', 'date'],
            'pickup_window_end' => ['nullable', 'date', 'after:pickup_window_start'],
            'terms_accepted' => ['accepted'],
            'special_requirements' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $routeMode = $this->input('route_mode');
            $startDate = $this->date('start_date');
            $endDate = $this->date('end_date');
            if (! $startDate || ! $endDate) {
                return;
            }

            $availability = app(ContainerAvailabilityService::class);

            if ($routeMode === 'ports') {
                $originId = (int) $this->input('origin_port_id');
                if ($originId > 0) {
                    $readyOrigins = $availability->portIdsWithAvailableContainerAtPort();
                    if (! in_array($originId, $readyOrigins, true)) {
                        $validator->errors()->add(
                            'origin_port_id',
                            'No available container is currently at the selected origin port.'
                        );
                    }
                }
            }

            $ctx = $availability->resolveRouteContext([
                'route_mode' => $routeMode,
                'route_id' => $this->input('route_id'),
                'origin_port_id' => $this->input('origin_port_id'),
                'destination_port_id' => $this->input('destination_port_id'),
                'priority' => $this->input('priority'),
                'routing_priority' => $this->input('routing_priority'),
            ]);

            if (! ($ctx['path_found'] ?? true)) {
                if ($routeMode === 'ports') {
                    $validator->errors()->add(
                        'destination_port_id',
                        'No open shipping route connects these ports. Try a different pair or contact operations.'
                    );
                } else {
                    $validator->errors()->add('route_id', 'The selected route is not available.');
                }
            }

            if ($routeMode === 'ports' && ($ctx['path_found'] ?? true)) {
                $originId = (int) $this->input('origin_port_id');
                $destId = (int) $this->input('destination_port_id');
                if ($originId > 0 && $destId > 0) {
                    $reachable = app(RoutePathfinderService::class)->reachablePortIds($originId);
                    if (! in_array($destId, $reachable, true)) {
                        $validator->errors()->add(
                            'destination_port_id',
                            'Selected destination is not reachable from this origin via open routes.'
                        );
                    }
                }
            }

            $originForSchedule = (int) ($ctx['origin_port_id'] ?? 0);
            if ($originForSchedule <= 0 && $routeMode === 'route') {
                $rid = (int) $this->input('route_id');
                if ($rid > 0) {
                    $originForSchedule = (int) (ShippingRoute::query()->whereKey($rid)->value('origin_port_id') ?? 0);
                }
            }
            if ($originForSchedule <= 0 && $routeMode === 'ports') {
                $originForSchedule = (int) $this->input('origin_port_id');
            }
            if ($originForSchedule > 0) {
                $departure = app(VesselPortScheduleService::class)->nextDepartureWindowAtPort(
                    $originForSchedule,
                    CarbonImmutable::now()->startOfDay(),
                    max(1, (int) config('logistics.vessel_forecast_days', 30)),
                );
                if ($departure !== null) {
                    $loadDays = max(0, (int) config('logistics.port_operations_min_days', 2));
                    $maxStart = $departure->subDays($loadDays)->startOfDay();
                    if ($startDate->copy()->startOfDay()->gt($maxStart)) {
                        $validator->errors()->add(
                            'start_date',
                            'Start date must be on or before '.$maxStart->format('Y-m-d').' (latest cargo-ready date before vessel departure minus loading time).'
                        );
                    }
                }
            }

            $spanDays = (int) ($ctx['min_rental_span_days'] ?? 0);
            if ($spanDays > 0) {
                $minEnd = $startDate->copy()->addDays($spanDays);
                if ($endDate->lt($minEnd)) {
                    $validator->errors()->add(
                        'end_date',
                        "Rental must span at least {$spanDays} day(s) for transit, port handling, and post-arrival buffer (minimum end date: {$minEnd->format('Y-m-d')})."
                    );
                }
            }

            $pickupStart = $this->date('pickup_window_start');
            $pickupEnd = $this->date('pickup_window_end');
            if ($pickupStart || $pickupEnd) {
                if ($pickupStart && ($pickupStart->lt($startDate) || $pickupStart->gt($endDate))) {
                    $validator->errors()->add('pickup_window_start', 'Pickup window must fall within the rental period (start to end date).');
                }
                if ($pickupEnd && ($pickupEnd->lt($startDate) || $pickupEnd->gt($endDate))) {
                    $validator->errors()->add('pickup_window_end', 'Pickup window must fall within the rental period (start to end date).');
                }
                if ($pickupStart && $pickupEnd && $pickupEnd->lte($pickupStart)) {
                    $validator->errors()->add('pickup_window_end', 'Pickup window end must be after pickup window start.');
                }
            }
        });
    }
}
