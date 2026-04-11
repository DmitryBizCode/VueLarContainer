<?php

namespace App\Http\Requests;

use App\Models\Route as ShippingRoute;
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
            'incoterm' => ['nullable', Rule::in(['EXW', 'FCA', 'FOB', 'CFR', 'CIF', 'DAP', 'DDP'])],
            'loading_type' => ['required', Rule::in(['fcl', 'lcl'])],
            'delivery_mode' => ['required', Rule::in(['port_to_port'])],
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

            $estimatedDays = null;
            if ($routeMode === 'route' && $this->input('route_id')) {
                $route = ShippingRoute::query()->find((int) $this->input('route_id'));
                $estimatedDays = $route?->estimated_days;
            } elseif ($routeMode === 'ports' && $this->input('origin_port_id') && $this->input('destination_port_id')) {
                $route = ShippingRoute::query()
                    ->where('origin_port_id', (int) $this->input('origin_port_id'))
                    ->where('destination_port_id', (int) $this->input('destination_port_id'))
                    ->first();
                $estimatedDays = $route?->estimated_days;
            }

            if ($estimatedDays !== null && $estimatedDays > 0) {
                $bufferDays = 3;
                $minEnd = $startDate->copy()->addDays($estimatedDays + $bufferDays);
                if ($endDate->lt($minEnd)) {
                    $validator->errors()->add(
                        'end_date',
                        'Rental period must be at least '.($estimatedDays + $bufferDays)." days for this route (minimum end date: {$minEnd->format('Y-m-d')})."
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
