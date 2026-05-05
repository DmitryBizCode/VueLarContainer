<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\VerifiesRentalIsIotEligible;
use App\Http\Requests\StoreRentalRequest;
use App\Http\Requests\UpdateRentalStatusRequest;
use App\Models\Container;
use App\Models\Country;
use App\Models\Notification;
use App\Models\Port;
use App\Models\Rental;
use App\Models\Route as ShippingRoute;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\ContainerAvailabilityService;
use App\Services\IotAuditChainService;
use App\Services\MonitorChartsService;
use App\Services\Notifications\NotificationService;
use App\Services\RentalPricingService;
use App\Services\RentalRouteFeasibilityService;
use App\Services\RoutePathfinderService;
use App\Services\RouteValidationService;
use App\Services\TelemetryAnalyticsService;
use App\Services\VesselPortScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RentalController extends Controller
{
    use VerifiesRentalIsIotEligible;

    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly ContainerAvailabilityService $availabilityService,
        private readonly RentalPricingService $pricingService,
        private readonly TelemetryAnalyticsService $telemetryAnalytics,
        private readonly MonitorChartsService $monitorCharts,
        private readonly VesselPortScheduleService $vesselSchedule,
        private readonly RouteValidationService $routeValidation,
        private readonly RentalRouteFeasibilityService $feasibility,
        private readonly RoutePathfinderService $pathfinder,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('rentals.request.create');
    }

    public function create(Request $request): Response
    {
        $routesQuery = ShippingRoute::query()
            ->with(['originPort.country', 'destinationPort.country'])
            ->where('route_status', 'open')
            ->orderBy('distance');

        if (! config('logistics.rental_request_show_all_open_routes', false)) {
            $ids = $this->availabilityService->openRouteIdsWithAvailableContainerAtOrigin();
            if ($ids !== []) {
                $routesQuery->whereIn('id', $ids);
            } else {
                $routesQuery->whereRaw('1 = 0');
            }
        }

        $routes = $routesQuery->get();

        $ports = Port::query()
            ->with('country')
            ->orderBy('name')
            ->get();

        $portSchedules = $this->availabilityService->portSchedulesWithAvailableContainers();
        $scheduleByPortId = collect($portSchedules)->keyBy('port_id');
        $showAllOpenRoutes = (bool) config('logistics.rental_request_show_all_open_routes', false);
        if (! $showAllOpenRoutes) {
            // Match route list: origins already have at least one available container.
            $originPortIds = $routes->pluck('origin_port_id')->unique()->filter()->map(fn ($id) => (int) $id)->values()->all();
            $originPorts = $ports->whereIn('id', $originPortIds)->values();
        } else {
            $originPorts = $scheduleByPortId->isEmpty()
                ? collect()
                : $ports->whereIn('id', $scheduleByPortId->keys()->all())->values();
        }

        // User-only on non-admin pages: do not show other users' pending approvals.
        $pendingApprovals = collect([]);

        $myRecentRequests = Rental::query()
            ->with(['container', 'originPort.country', 'destinationPort.country'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(8)
            ->get();

        $allCountries = Country::query()->orderBy('name')->get();
        $userCountryId = $request->user()?->country_id;
        $countries = $allCountries->sortByDesc(fn (Country $c) => (int) ($c->id === $userCountryId))->values()->map(fn (Country $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'iso_code' => $c->iso_code,
            'phone_code' => $c->phone_code ?? '',
        ])->all();

        return Inertia::render('Operations/ContainerRentalRequest/Index', [
            'countries' => $countries,
            'user_country_id' => $userCountryId,
            'routes' => $routes->map(fn (ShippingRoute $route) => [
                'id' => $route->id,
                'origin_port_id' => $route->origin_port_id,
                'destination_port_id' => $route->destination_port_id,
                'estimated_days' => $route->estimated_days,
                'distance' => (float) $route->distance,
                'origin_vessel_departure_at' => $scheduleByPortId->get($route->origin_port_id)['vessel_departure_at'] ?? null,
                'label' => sprintf(
                    '%s, %s -> %s, %s',
                    $route->originPort?->name ?? 'N/A',
                    $route->originPort?->country?->name ?? 'N/A',
                    $route->destinationPort?->name ?? 'N/A',
                    $route->destinationPort?->country?->name ?? 'N/A'
                ),
            ]),
            'ports' => $ports->map(fn (Port $port) => [
                'id' => $port->id,
                'name' => $port->name,
                'city' => $port->city,
                'country_name' => $port->country?->name,
                'latitude' => $port->latitude !== null ? (float) $port->latitude : null,
                'longitude' => $port->longitude !== null ? (float) $port->longitude : null,
                'label' => sprintf('%s, %s', $port->name, $port->country?->name ?? 'N/A'),
            ]),
            'origin_ports' => $originPorts->map(fn (Port $port) => [
                'id' => $port->id,
                'name' => $port->name,
                'city' => $port->city,
                'country_name' => $port->country?->name,
                'latitude' => $port->latitude !== null ? (float) $port->latitude : null,
                'longitude' => $port->longitude !== null ? (float) $port->longitude : null,
                'label' => sprintf('%s, %s', $port->name, $port->country?->name ?? 'N/A'),
                'vessel_departure_at' => $scheduleByPortId->get($port->id)['vessel_departure_at'] ?? null,
            ])->values()->all(),
            'cargo_types' => [
                ['value' => 'electronics', 'label' => 'Electronics'],
                ['value' => 'furniture', 'label' => 'Furniture'],
                ['value' => 'clothing', 'label' => 'Clothing'],
                ['value' => 'food', 'label' => 'Food'],
                ['value' => 'machinery', 'label' => 'Machinery'],
                ['value' => 'other', 'label' => 'Other'],
            ],
            'priority_levels' => [
                ['value' => 'normal', 'label' => 'Normal'],
                ['value' => 'urgent', 'label' => 'Urgent'],
                ['value' => 'express', 'label' => 'Express SLA'],
            ],
            'incoterms' => ['EXW', 'FCA', 'FOB', 'CFR', 'CIF', 'DAP', 'DDP'],
            'delivery_modes' => [
                ['value' => 'port_to_port', 'label' => 'Port to Port'],
                ['value' => 'door_to_port', 'label' => 'Door to Port'],
                ['value' => 'port_to_door', 'label' => 'Port to Door'],
                ['value' => 'door_to_door', 'label' => 'Door to Door'],
            ],
            'loading_types' => [
                ['value' => 'fcl', 'label' => 'FCL (Full Container Load)'],
                ['value' => 'lcl', 'label' => 'LCL (Less than Container Load)'],
            ],
            'sustainability_options' => [
                ['value' => 'standard', 'label' => 'Standard routing'],
                ['value' => 'eco_optimized', 'label' => 'Eco optimized routing'],
                ['value' => 'low_emission', 'label' => 'Low emission handling'],
            ],
            'logistics_config' => [
                'port_operations_max_days' => (int) config('logistics.port_operations_max_days', 4),
                'post_arrival_min_days' => (int) config('logistics.post_arrival_min_days', 2),
                'post_arrival_max_days' => (int) config('logistics.post_arrival_max_days', 3),
                'loading_buffer_days' => 3,
                'time_load_days' => (int) config('logistics.port_operations_min_days', 2),
                'time_unload_days' => max(
                    (int) config('logistics.post_arrival_min_days', 2),
                    (int) config('logistics.post_arrival_max_days', 3)
                ),
            ],
            'routing_priority_options' => [
                ['value' => '', 'label' => 'Auto (from SLA priority)'],
                ['value' => 'speed', 'label' => 'Fastest sea path'],
                ['value' => 'cost', 'label' => 'Lowest cost path'],
            ],
            'pending_approvals' => $pendingApprovals->map(fn (Rental $rental) => [
                'id' => $rental->id,
                'customer' => trim(($rental->user?->first_name ?? '').' '.($rental->user?->last_name ?? '')),
                'container_serial' => $rental->container?->serial_number,
                'origin' => $rental->originPort?->name,
                'destination' => $rental->destinationPort?->name,
                'price' => (float) $rental->price,
                'status' => $rental->status,
                'created_at' => $rental->created_at,
            ]),
            'my_recent_requests' => $myRecentRequests->map(fn (Rental $rental) => [
                'id' => $rental->id,
                'container_serial' => $rental->container?->serial_number,
                'container_operational_status' => $rental->container?->current_status,
                'status' => $rental->status,
                'payment_status' => $rental->payment_status,
                'rejection_reason' => $rental->rejection_reason,
                'cancellation_reason' => $rental->cancellation_reason,
                'price' => (float) $rental->price,
                'start_date' => $rental->start_date,
                'end_date' => $rental->end_date,
                'created_at' => $rental->created_at,
                'container_iot_active' => (bool) ($rental->container?->iot_active ?? false),
                'can_view_iot_monitor' => $rental->canAccessIotMonitor() && (bool) ($rental->container?->iot_active ?? false),
            ]),
        ]);
    }

    public function reachableDestinations(Request $request): JsonResponse
    {
        $originPortId = (int) $request->query('origin_port_id', 0);
        if ($originPortId <= 0) {
            return response()->json(['port_ids' => []]);
        }

        return response()->json([
            'port_ids' => $this->pathfinder->reachablePortIds($originPortId),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'route_mode' => ['required', 'in:route,ports'],
            'route_id' => ['nullable', 'integer', 'exists:routes,id'],
            'origin_port_id' => ['nullable', 'integer', 'exists:ports,id'],
            'destination_port_id' => ['nullable', 'integer', 'exists:ports,id', 'different:origin_port_id'],
            'container_id' => ['nullable', 'integer', 'exists:containers,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'cargo_types' => ['required', 'array', 'min:1'],
            'cargo_types.*' => ['string'],
            'requested_weight' => ['nullable', 'numeric', 'min:0'],
            'cargo_volume_cbm' => ['nullable', 'numeric', 'min:0'],
            'package_count' => ['nullable', 'integer', 'min:1'],
            'cargo_value' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['required', 'in:normal,urgent,express'],
            'delivery_mode' => ['required', 'in:port_to_port,door_to_port,port_to_door,door_to_door'],
            'loading_type' => ['required', 'in:fcl,lcl'],
            'sustainability_pref' => ['required', 'in:standard,eco_optimized,low_emission'],
            'insurance_required' => ['required', 'boolean'],
            'requires_customs_clearance' => ['required', 'boolean'],
            'hazardous_material' => ['required', 'boolean'],
            'requires_escort' => ['required', 'boolean'],
            'seal_required' => ['required', 'boolean'],
            'routing_priority' => ['nullable', 'string', 'in:speed,cost,balanced'],
        ]);

        $startDate = CarbonImmutable::parse($validated['start_date']);
        $endDate = CarbonImmutable::parse($validated['end_date']);
        $routeContext = $this->availabilityService->resolveRouteContext($validated);

        if (! ($routeContext['path_found'] ?? true)) {
            return response()->json([
                'message' => 'No open shipping route connects the selected ports. Choose another pair or contact operations.',
            ], 422);
        }

        // Ensure every leg is drawable (has sea_path geometry). Prevent rentals that cannot be shown on the map.
        $legIds = collect($routeContext['route_legs'] ?? [])
            ->pluck('route_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
        if ($legIds !== []) {
            $legs = ShippingRoute::query()->whereIn('id', $legIds)->get();
            $invalid = $legs->first(fn (ShippingRoute $r) => ! $this->routeValidation->isDrawable($r));
            if ($invalid) {
                return response()->json([
                    'message' => 'Selected routing includes a leg without configured sea geometry. Please choose another route or contact operations.',
                ], 422);
            }
        }

        $routeLegs = is_array($routeContext['route_legs'] ?? null) ? $routeContext['route_legs'] : [];
        $plan = $this->feasibility->buildPlan($routeLegs, $startDate, $validated['routing_priority'] ?? null);
        if (! ($plan['can_create_rental'] ?? true)) {
            $message = ($plan['warnings'][0] ?? null) ?: 'Selected routing cannot be fulfilled with current vessel availability.';

            return response()->json([
                'message' => $message,
                'route_plan' => $plan,
            ], 422);
        }

        // Override min span with feasibility (includes waiting/handling).
        $routeContext['min_rental_span_days'] = (int) ($plan['minimum_rental_days'] ?? ($routeContext['min_rental_span_days'] ?? 0));

        $spanDays = (int) ($routeContext['min_rental_span_days'] ?? 0);
        if ($spanDays > 0) {
            $minEnd = $startDate->addDays($spanDays);
            if ($endDate->lt($minEnd)) {
                return response()->json([
                    'message' => 'Rental period must cover transit, port handling, and post-arrival buffer (minimum '.$spanDays.' days from start for this routing).',
                    'route_plan' => $plan,
                ], 422);
            }
        }

        $availableContainers = $this->availabilityService->findAvailableContainers(
            $startDate,
            $endDate,
            $routeContext['origin_port_id'],
            isset($validated['requested_weight']) ? (float) $validated['requested_weight'] : null,
            $validated['cargo_types']
        );

        $selectedForPricing = null;
        if (! empty($validated['container_id'])) {
            $selectedForPricing = $availableContainers->firstWhere('id', (int) $validated['container_id']);
        }
        if (! $selectedForPricing instanceof Container && $availableContainers->isNotEmpty()) {
            $selectedForPricing = $availableContainers->first();
        }

        $priceBreakdown = null;
        $estimatedPrice = 0.0;
        if ($selectedForPricing instanceof Container) {
            $user = User::query()
                ->with('country')
                ->findOrFail($request->user()->id);

            $priceBreakdown = $this->pricingService->calculate(
                $user,
                $selectedForPricing,
                $startDate,
                $endDate,
                (float) $routeContext['distance'],
                (int) $routeContext['estimated_days'],
                $validated['cargo_types'],
                isset($validated['requested_weight']) ? (float) $validated['requested_weight'] : null,
                isset($validated['cargo_volume_cbm']) ? (float) $validated['cargo_volume_cbm'] : null,
                isset($validated['package_count']) ? (int) $validated['package_count'] : null,
                isset($validated['cargo_value']) ? (float) $validated['cargo_value'] : null,
                (string) ($validated['priority'] ?? 'normal'),
                (string) ($validated['delivery_mode'] ?? 'port_to_port'),
                (string) ($validated['loading_type'] ?? 'fcl'),
                (string) ($validated['sustainability_pref'] ?? 'standard'),
                (bool) ($validated['insurance_required'] ?? false),
                (bool) ($validated['hazardous_material'] ?? false),
                (bool) ($validated['requires_customs_clearance'] ?? false),
                (bool) ($validated['requires_escort'] ?? false),
                (bool) ($validated['seal_required'] ?? false)
            );
            $priceBreakdown['route_legs'] = $routeContext['route_legs'] ?? [];
            $priceBreakdown['routing_mode'] = $routeContext['routing_mode'] ?? null;
            $estimatedPrice = (float) $priceBreakdown['estimated_total'];
        }

        // Enrich route legs with port names + pre-assign a vessel preview at the first-leg origin,
        // so the UI can surface transshipment details and the barge/vessel that will likely handle the first leg.
        $legsEnriched = [];
        $legPortIds = [];
        foreach (($routeContext['route_legs'] ?? []) as $leg) {
            if (! empty($leg['origin_port_id'])) {
                $legPortIds[(int) $leg['origin_port_id']] = true;
            }
            if (! empty($leg['destination_port_id'])) {
                $legPortIds[(int) $leg['destination_port_id']] = true;
            }
        }
        $portNameById = $legPortIds === []
            ? []
            : Port::query()->whereIn('id', array_keys($legPortIds))->pluck('name', 'id')->all();
        foreach (($routeContext['route_legs'] ?? []) as $leg) {
            $oid = isset($leg['origin_port_id']) ? (int) $leg['origin_port_id'] : null;
            $did = isset($leg['destination_port_id']) ? (int) $leg['destination_port_id'] : null;
            $legsEnriched[] = [
                'route_id' => isset($leg['route_id']) ? (int) $leg['route_id'] : null,
                'origin_port_id' => $oid,
                'destination_port_id' => $did,
                'origin_name' => $oid !== null ? ($portNameById[$oid] ?? null) : null,
                'destination_name' => $did !== null ? ($portNameById[$did] ?? null) : null,
                'estimated_days' => isset($leg['estimated_days']) ? (int) $leg['estimated_days'] : null,
                'distance' => isset($leg['distance']) ? (float) $leg['distance'] : null,
            ];
        }

        $assignedVessel = null;
        $firstSegVessel = $plan['segments'][0]['vessel'] ?? null;
        if (is_array($firstSegVessel) && isset($firstSegVessel['id'])) {
            $assignedVessel = $firstSegVessel + ['current_port_id' => $routeContext['origin_port_id'] ?? null];
        }

        $routeContextOut = $routeContext + ['route_legs_named' => $legsEnriched];
        $routeContextOut['is_direct'] = count($legsEnriched) <= 1;
        $routeContextOut['transfer_ports'] = array_values(array_filter(array_map(
            static fn (array $leg) => $leg['destination_name'] ?? null,
            array_slice($legsEnriched, 0, max(0, count($legsEnriched) - 1))
        )));

        return response()->json([
            'route_context' => $routeContextOut,
            'route_plan' => $plan,
            'assigned_vessel' => $assignedVessel,
            'available_containers' => $availableContainers->map(fn (Container $container) => [
                'id' => $container->id,
                'serial_number' => $container->serial_number,
                'type' => $container->type,
                'current_status' => $container->current_status,
                'owner_name' => $container->owner?->name,
                'country_name' => $container->currentPort?->country?->name,
                'current_port_name' => $container->currentPort?->name,
                'dimensions' => sprintf('%sm x %sm x %sm', $container->width, $container->length, $container->height),
                'max_weight' => (float) $container->max_weight,
                'iot_active' => (bool) $container->iot_active,
            ])->values(),
            'estimated_price' => $estimatedPrice,
            'price_breakdown' => $priceBreakdown,
        ]);
    }

    public function store(StoreRentalRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $startDate = CarbonImmutable::parse($validated['start_date']);
        $endDate = CarbonImmutable::parse($validated['end_date']);
        $routeContext = $this->availabilityService->resolveRouteContext($validated);

        if (! ($routeContext['path_found'] ?? true)) {
            return back()->withErrors([
                'route_mode' => 'No open shipping route is available for the selected ports.',
            ]);
        }

        $routeLegs = is_array($routeContext['route_legs'] ?? null) ? $routeContext['route_legs'] : [];
        $plan = $this->feasibility->buildPlan($routeLegs, $startDate, $validated['routing_priority'] ?? null);
        if (! ($plan['can_create_rental'] ?? true)) {
            return back()->withErrors([
                'route_mode' => ($plan['warnings'][0] ?? null) ?: 'Selected routing cannot be fulfilled with current vessel availability.',
            ]);
        }

        $spanDays = (int) ($plan['minimum_rental_days'] ?? ($routeContext['min_rental_span_days'] ?? 0));
        if ($spanDays > 0) {
            $minEnd = $startDate->addDays($spanDays);
            if ($endDate->lt($minEnd)) {
                return back()->withErrors([
                    'end_date' => "Rental must span at least {$spanDays} day(s) for this routing (minimum end date: {$minEnd->format('Y-m-d')}).",
                ]);
            }
        }

        $selectedContainerId = (int) $validated['container_id'];

        $availableContainers = $this->availabilityService->findAvailableContainers(
            $startDate,
            $endDate,
            $routeContext['origin_port_id'],
            isset($validated['requested_weight']) ? (float) $validated['requested_weight'] : null,
            $validated['cargo_types']
        );

        $selectedContainer = $availableContainers->firstWhere('id', $selectedContainerId);
        if (! $selectedContainer instanceof Container) {
            return back()->withErrors([
                'container_id' => 'Selected container is no longer available for this period.',
            ]);
        }

        $user = User::query()->with('country')->findOrFail($request->user()->id);

        $priceBreakdown = $this->pricingService->calculate(
            $user,
            $selectedContainer,
            $startDate,
            $endDate,
            (float) $routeContext['distance'],
            (int) $routeContext['estimated_days'],
            $validated['cargo_types'],
            isset($validated['requested_weight']) ? (float) $validated['requested_weight'] : null,
            isset($validated['cargo_volume_cbm']) ? (float) $validated['cargo_volume_cbm'] : null,
            isset($validated['package_count']) ? (int) $validated['package_count'] : null,
            isset($validated['cargo_value']) ? (float) $validated['cargo_value'] : null,
            (string) ($validated['priority'] ?? 'normal'),
            (string) ($validated['delivery_mode'] ?? 'port_to_port'),
            (string) ($validated['loading_type'] ?? 'fcl'),
            (string) ($validated['sustainability_pref'] ?? 'standard'),
            (bool) ($validated['insurance_required'] ?? false),
            (bool) ($validated['hazardous_material'] ?? false),
            (bool) ($validated['requires_customs_clearance'] ?? false),
            (bool) ($validated['requires_escort'] ?? false),
            (bool) ($validated['seal_required'] ?? false)
        );
        $priceBreakdown['route_legs'] = $routeContext['route_legs'] ?? [];
        $priceBreakdown['routing_mode'] = $routeContext['routing_mode'] ?? null;

        $rental = DB::transaction(function () use ($request, $validated, $routeContext, $startDate, $endDate, $priceBreakdown, $selectedContainerId) {
            // Pessimistic lock — prevents double-booking under concurrent requests.
            $isAvailable = Container::query()
                ->lockForUpdate()
                ->where('id', $selectedContainerId)
                ->where('current_status', 'available')
                ->whereDoesntHave('rentals', function ($q) use ($startDate, $endDate) {
                    $q->whereIn('status', ['approved', 'scheduled', 'in_progress'])
                        ->where(function ($d) use ($startDate, $endDate) {
                            $d->whereBetween('start_date', [$startDate, $endDate])
                                ->orWhereBetween('end_date', [$startDate, $endDate])
                                ->orWhere(function ($deep) use ($startDate, $endDate) {
                                    $deep->where('start_date', '<=', $startDate)
                                        ->where(function ($o) use ($endDate) {
                                            $o->whereNull('end_date')->orWhere('end_date', '>=', $endDate);
                                        });
                                });
                        });
                })
                ->exists();

            if (! $isAvailable) {
                return null;
            }

            $created = Rental::query()->create([
                'user_id' => $request->user()->id,
                'container_id' => $selectedContainerId,
                'route_id' => $routeContext['route_id'],
                'origin_port_id' => $routeContext['origin_port_id'],
                'destination_port_id' => $routeContext['destination_port_id'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rental_days' => (int) $priceBreakdown['days'],
                'cargo_types' => $validated['cargo_types'],
                'cargo_details' => $validated['cargo_details'] ?? null,
                'requested_weight' => isset($validated['requested_weight']) ? (float) $validated['requested_weight'] : null,
                'cargo_volume_cbm' => isset($validated['cargo_volume_cbm']) ? (float) $validated['cargo_volume_cbm'] : null,
                'package_count' => isset($validated['package_count']) ? (int) $validated['package_count'] : null,
                'cargo_value' => isset($validated['cargo_value']) ? (float) $validated['cargo_value'] : null,
                'priority' => $validated['priority'],
                'routing_priority' => ! empty($validated['routing_priority']) ? (string) $validated['routing_priority'] : null,
                'incoterm' => $validated['incoterm'] ?? null,
                'loading_type' => $validated['loading_type'],
                'delivery_mode' => $validated['delivery_mode'],
                'sustainability_pref' => $validated['sustainability_pref'],
                'insurance_required' => (bool) $validated['insurance_required'],
                'requires_customs_clearance' => (bool) $validated['requires_customs_clearance'],
                'hazardous_material' => (bool) $validated['hazardous_material'],
                'requires_escort' => (bool) $validated['requires_escort'],
                'seal_required' => (bool) $validated['seal_required'],
                'un_number' => $validated['un_number'] ?? null,
                'dangerous_goods_class' => $validated['dangerous_goods_class'] ?? null,
                'origin_customs_code' => $validated['origin_customs_code'] ?? null,
                'destination_customs_code' => $validated['destination_customs_code'] ?? null,
                'contact_name' => $validated['contact_name'],
                'contact_phone' => $validated['contact_phone'],
                'pickup_address' => $validated['pickup_address'] ?? null,
                'delivery_address' => $validated['delivery_address'] ?? null,
                'pickup_window_start' => $validated['pickup_window_start'] ?? null,
                'pickup_window_end' => $validated['pickup_window_end'] ?? null,
                'quote_expires_at' => now()->addDays(3),
                'terms_accepted' => true,
                'special_requirements' => $validated['special_requirements'] ?? null,
                'estimated_distance' => (float) $routeContext['distance'],
                'price' => (float) $priceBreakdown['estimated_total'],
                'price_breakdown' => $priceBreakdown,
                'status' => 'pending_approval',
                'payment_status' => 'pending',
                'description' => $validated['description'] ?? null,
            ]);

            $this->logRentalActivity((int) $request->user()->id, 'submitted_for_approval', $created, null, $request);
            $this->createNotification(
                (int) $request->user()->id,
                'Rental request submitted',
                'Your rental request is pending approval.',
                'info'
            );

            return $created;
        });

        if (! $rental instanceof Rental) {
            return back()->withErrors([
                'container_id' => 'Selected container is no longer available for this period.',
            ]);
        }

        return redirect()
            ->route('rentals.request.create')
            ->with('status', 'Your rental request was submitted for approval.');
    }

    public function show(Rental $rental): RedirectResponse
    {
        return redirect()->route('rentals.center');
    }

    public function monitor(Request $request, Rental $rental, IotAuditChainService $iotAudit): Response
    {
        $user = $request->user();
        if ((int) $rental->user_id !== (int) $user->id) {
            abort(404);
        }

        $this->verifyRentalCanAccessIotMonitor($rental);

        $rental->load([
            'container.owner',
            'container.currentPort.country',
            'container.containerSensors.sensorType',
            'originPort.country',
            'destinationPort.country',
            'route.originPort.country',
            'route.destinationPort.country',
        ]);

        $container = $rental->container;

        $now = CarbonImmutable::now();
        $defaultHours = 24;
        $stepHours = 2;

        $fromInput = $request->query('from');
        $toInput = $request->query('to');
        if ($fromInput && $toInput) {
            $from = CarbonImmutable::parse($fromInput);
            $to = CarbonImmutable::parse($toInput);
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }
            $maxRange = 168;
            if ($from->diffInHours($to) > $maxRange) {
                $to = $from->addHours($maxRange);
            }
        } else {
            $to = $now;
            $from = $now->subHours($defaultHours);
        }

        $iotCharts = $this->monitorCharts->trimPayloadForInertiaInitialLoad(
            $this->monitorCharts->build($rental, $from, $to)
        );

        $iotLatest = null;
        if ($container) {
            $iotLatest = $this->telemetryAnalytics->latestForContainer(
                (int) $container->id,
                (int) $rental->id,
                (int) $rental->user_id
            );
        }

        $historyRentals = Rental::query()
            ->with(['originPort', 'destinationPort'])
            ->where('container_id', $rental->container_id)
            ->where('id', '!=', $rental->id)
            ->orderByDesc('start_date')
            ->limit(6)
            ->get();

        $opsSummary = null;

        $iotAuditEvents = [];
        if ($container) {
            $iotAuditEvents = $iotAudit->forRental((int) $rental->id, (int) $container->id, 50)
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'sequence' => $e->sequence,
                    'event_type' => $e->event_type,
                    'payload' => $e->payload,
                    'prev_hash' => $e->prev_hash,
                    'row_hash' => $e->row_hash,
                    'created_at' => $e->created_at->toIso8601String(),
                    'user' => $e->user ? [
                        'id' => $e->user->id,
                        'name' => trim(($e->user->first_name ?? '').' '.($e->user->last_name ?? '')),
                    ] : null,
                ])
                ->values()
                ->all();
        }

        return Inertia::render('Rentals/Monitor', [
            'rental' => [
                'id' => $rental->id,
                'status' => $rental->status,
                'rejection_reason' => $rental->rejection_reason,
                'cancellation_reason' => $rental->cancellation_reason,
                'is_telemetry_active' => (bool) $rental->is_telemetry_active,
                'payment_status' => $rental->payment_status,
                'start_date' => $rental->start_date,
                'end_date' => $rental->end_date,
                'actual_return_date' => $rental->actual_return_date,
                'rental_days' => $rental->rental_days,
                'estimated_distance' => $rental->estimated_distance,
                'price' => (float) $rental->price,
                'price_breakdown' => $rental->price_breakdown,
                'origin_port' => $rental->originPort ? [
                    'id' => $rental->originPort->id,
                    'name' => $rental->originPort->name,
                    'city' => $rental->originPort->city,
                    'country' => $rental->originPort->country?->name,
                ] : null,
                'destination_port' => $rental->destinationPort ? [
                    'id' => $rental->destinationPort->id,
                    'name' => $rental->destinationPort->name,
                    'city' => $rental->destinationPort->city,
                    'country' => $rental->destinationPort->country?->name,
                ] : null,
            ],
            'container' => $container ? [
                'id' => $container->id,
                'serial_number' => $container->serial_number,
                'type' => $container->type,
                'width' => (float) $container->width,
                'length' => (float) $container->length,
                'height' => (float) $container->height,
                'max_weight' => (float) $container->max_weight,
                'iot_active' => (bool) $container->iot_active,
                'current_status' => $container->current_status,
                'current_port' => $container->currentPort ? [
                    'id' => $container->currentPort->id,
                    'name' => $container->currentPort->name,
                    'city' => $container->currentPort->city,
                    'country' => $container->currentPort->country?->name,
                ] : null,
            ] : null,
            'iot_enabled' => (bool) ($container?->iot_active ?? false),
            'iot_latest' => $iotLatest,
            'iot_charts' => $iotCharts,
            'iot_audit' => $iotAuditEvents,
            'history_rentals' => $historyRentals->map(static fn (Rental $item) => [
                'id' => $item->id,
                'start_date' => $item->start_date,
                'end_date' => $item->end_date,
                'status' => $item->status,
                'payment_status' => $item->payment_status,
                'origin_port_name' => $item->originPort?->name,
                'destination_port_name' => $item->destinationPort?->name,
                'price' => (float) $item->price,
            ])->values(),
            'ops_summary' => $opsSummary,
        ]);
    }

    public function container3d(Request $request, Rental $rental): Response|RedirectResponse
    {
        $user = $request->user();
        if ((int) $rental->user_id !== (int) $user->id) {
            abort(404);
        }

        $this->verifyRentalCanAccessIotMonitor($rental);

        $rental->load(['container.currentPort.country', 'originPort', 'destinationPort']);
        $container = $rental->container;

        if (! $container) {
            return redirect()->route('rentals.center')->with('error', 'No container assigned');
        }

        $snapshot = \App\Models\ContainerSimulationSnapshot::query()->firstWhere('container_id', $container->id);
        $actuators = $snapshot?->actuators ?? [];

        return Inertia::render('Rentals/Container3D', [
            'rental' => [
                'id' => $rental->id,
                'origin_port' => $rental->originPort ? ['name' => $rental->originPort->name] : null,
                'destination_port' => $rental->destinationPort ? ['name' => $rental->destinationPort->name] : null,
            ],
            'container' => [
                'id' => $container->id,
                'serial_number' => $container->serial_number,
                'type' => $container->type,
                'width' => (float) $container->width,
                'length' => (float) $container->length,
                'height' => (float) $container->height,
                'manufacture_date' => $container->manufacture_date?->format('Y-m-d'),
            ],
            'actuators' => [
                'acStatus' => (bool) ($actuators['acStatus'] ?? false),
                'acTemp' => (float) ($actuators['acTemp'] ?? 22),
                'humidifier' => (bool) ($actuators['humidifier'] ?? false),
                'heater' => (bool) ($actuators['heater'] ?? false),
                'ventilation' => (bool) ($actuators['ventilation'] ?? false),
                'mainLight' => (bool) ($actuators['mainLight'] ?? false),
                'irLamp' => (bool) ($actuators['irLamp'] ?? false),
                'pump' => (bool) ($actuators['pump'] ?? false),
                'doorOpen' => (bool) ($actuators['doorOpen'] ?? false),
                'freshenerOn' => (bool) ($actuators['freshenerOn'] ?? false),
            ],
        ]);
    }

    public function edit(Rental $rental): RedirectResponse
    {
        return redirect()->route('rentals.center');
    }

    public function update(UpdateRentalStatusRequest $request, Rental $rental): RedirectResponse
    {
        $user = $request->user();
        if ((int) $rental->user_id !== (int) $user->id) {
            abort(404);
        }

        $validated = $request->validated();
        $nextStatus = (string) $validated['status'];
        $currentStatus = (string) $rental->status;

        $adminOnlyStatuses = ['approved', 'rejected', 'scheduled', 'in_progress', 'completed'];
        if (in_array($nextStatus, $adminOnlyStatuses, true)) {
            return back()->withErrors([
                'status' => 'Only administrators can set this status.',
            ]);
        }

        if (! $this->isAllowedTransition($currentStatus, $nextStatus)) {
            return back()->withErrors([
                'status' => "Transition from '{$currentStatus}' to '{$nextStatus}' is not allowed.",
            ]);
        }

        DB::transaction(function () use ($request, $rental, $validated, $nextStatus) {
            $oldValues = [
                'status' => $rental->status,
                'payment_status' => $rental->payment_status,
                'reviewed_by' => $rental->reviewed_by,
                'reviewed_at' => $rental->reviewed_at,
                'rejection_reason' => $rental->rejection_reason,
                'cancellation_reason' => $rental->cancellation_reason,
            ];

            $rental->status = $nextStatus;
            if (in_array($nextStatus, ['approved', 'rejected'], true)) {
                $rental->reviewed_by = $request->user()->id;
                $rental->reviewed_at = now();
                $rental->payment_status = $nextStatus === 'approved' ? 'pending' : $rental->payment_status;
            } elseif (! empty($validated['payment_status'])) {
                $rental->payment_status = $validated['payment_status'];
            }

            if ($nextStatus === 'rejected') {
                $rental->rejection_reason = $validated['rejection_reason'] ?? null;
                $rental->cancellation_reason = null;
            } elseif ($nextStatus === 'cancelled') {
                $rental->cancellation_reason = $validated['cancellation_reason'] ?? null;
                $rental->rejection_reason = null;
            } else {
                $rental->rejection_reason = null;
                $rental->cancellation_reason = null;
            }

            $rental->save();

            $this->logRentalActivity((int) $request->user()->id, "status_changed_to_{$nextStatus}", $rental, $oldValues, $request);
            $this->createNotification(
                (int) $rental->user_id,
                "Rental #{$rental->id} status update",
                "Rental status changed to '{$nextStatus}'.",
                $nextStatus === 'rejected' ? 'warning' : 'info'
            );
        });

        return back()->with('status', "Rental #{$rental->id} updated.");
    }

    public function destroy(Request $request, Rental $rental): RedirectResponse
    {
        if ((int) $rental->user_id !== (int) $request->user()->id) {
            abort(404);
        }

        DB::transaction(function () use ($request, $rental) {
            $this->activityLog->log(
                $request->user()->id,
                'rental_deleted',
                'Rental',
                $rental->id,
                ['status' => $rental->status],
                null,
                "Rental #{$rental->id} deleted by user",
                $request
            );

            $rental->delete();
        });

        return redirect()->route('rentals.center')->with('status', 'Rental deleted.');
    }

    private function isAllowedTransition(string $currentStatus, string $nextStatus): bool
    {
        $allowedTransitions = [
            'draft' => ['pending_approval', 'cancelled'],
            'pending_approval' => ['approved', 'rejected', 'cancelled'],
            'approved' => ['scheduled', 'in_progress', 'cancelled'],
            'scheduled' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'rejected' => [],
            'completed' => [],
            'cancelled' => [],
        ];

        return in_array($nextStatus, $allowedTransitions[$currentStatus] ?? [], true);
    }

    private function logRentalActivity(int $userId, string $action, Rental $rental, ?array $oldValues = null, ?Request $request = null): void
    {
        $this->activityLog->log(
            $userId,
            $action,
            'Rental',
            $rental->id,
            $oldValues,
            ['status' => $rental->status, 'payment_status' => $rental->payment_status],
            "Rental #{$rental->id} ".str_replace('_', ' ', $action),
            $request ?? request()
        );
    }

    private function createNotification(int $userId, string $title, string $message, string $type): void
    {
        $exists = Notification::query()
            ->where('user_id', $userId)
            ->where('title', $title)
            ->where('message', $message)
            ->where('is_read', false)
            ->exists();

        if ($exists) {
            return;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        app(NotificationService::class)->notifyUserInApp(
            $user,
            $type,
            $title,
            $message,
            route('rentals.center'),
        );
    }
}
