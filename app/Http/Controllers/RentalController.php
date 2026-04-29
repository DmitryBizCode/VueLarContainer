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
use App\Services\RentalPricingService;
use App\Services\TelemetryAnalyticsService;
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
        private readonly ContainerAvailabilityService $availabilityService,
        private readonly RentalPricingService $pricingService,
        private readonly TelemetryAnalyticsService $telemetryAnalytics,
        private readonly MonitorChartsService $monitorCharts
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

        $readyOriginPortIds = $this->availabilityService->portIdsWithAvailableContainerAtPort();
        $originPorts = $readyOriginPortIds === []
            ? collect()
            : $ports->whereIn('id', $readyOriginPortIds)->values();

        $isOpsUser = in_array((string) ($request->user()->role ?? ''), ['admin', 'operator', 'ops'], true);
        $pendingApprovals = $isOpsUser
            ? Rental::query()
                ->with(['user', 'container', 'originPort.country', 'destinationPort.country'])
                ->where('status', 'pending_approval')
                ->latest()
                ->limit(10)
                ->get()
            : collect([]);

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

        return Inertia::render('Rentals/Request', [
            'countries' => $countries,
            'user_country_id' => $userCountryId,
            'routes' => $routes->map(fn (ShippingRoute $route) => [
                'id' => $route->id,
                'origin_port_id' => $route->origin_port_id,
                'destination_port_id' => $route->destination_port_id,
                'estimated_days' => $route->estimated_days,
                'distance' => (float) $route->distance,
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
                'post_arrival_min_days' => (int) config('logistics.post_arrival_min_days', 1),
                'loading_buffer_days' => 3,
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

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'route_mode' => ['required', 'in:route,ports'],
            'route_id' => ['nullable', 'integer', 'exists:routes,id'],
            'origin_port_id' => ['nullable', 'integer', 'exists:ports,id'],
            'destination_port_id' => ['nullable', 'integer', 'exists:ports,id', 'different:origin_port_id'],
            'container_id' => ['nullable', 'integer', 'exists:containers,id'],
            'start_date' => ['required', 'date'],
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
            'routing_priority' => ['nullable', 'string', 'in:speed,cost'],
        ]);

        $startDate = CarbonImmutable::parse($validated['start_date']);
        $endDate = CarbonImmutable::parse($validated['end_date']);
        $routeContext = $this->availabilityService->resolveRouteContext($validated);

        if (! ($routeContext['path_found'] ?? true)) {
            return response()->json([
                'message' => 'No open shipping route connects the selected ports. Choose another pair or contact operations.',
            ], 422);
        }

        $spanDays = (int) ($routeContext['min_rental_span_days'] ?? 0);
        if ($spanDays > 0) {
            $minEnd = $startDate->addDays($spanDays);
            if ($endDate->lt($minEnd)) {
                return response()->json([
                    'message' => 'Rental period must cover transit, port handling, and post-arrival buffer (minimum '.$spanDays.' days from start for this routing).',
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

        return response()->json([
            'route_context' => $routeContext,
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
                "Rental request #{$created->id} submitted",
                'Your rental request is pending approval.',
                'info'
            );

            return $created;
        });

        return redirect()
            ->route('rentals.request.create')
            ->with('status', "Rental request #{$rental->id} submitted for approval.");
    }

    public function show(Rental $rental): RedirectResponse
    {
        return redirect()->route('rentals.center');
    }

    public function monitor(Request $request, Rental $rental, IotAuditChainService $iotAudit): Response
    {
        $user = $request->user();
        $isOpsUser = in_array((string) ($user->role ?? ''), ['admin', 'operator', 'ops'], true);

        if (! $isOpsUser && (int) $rental->user_id !== (int) $user->id) {
            abort(403);
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
            ->where('container_id', $rental->container_id)
            ->where('id', '!=', $rental->id)
            ->orderByDesc('start_date')
            ->limit(6)
            ->get();

        $opsSummary = null;
        if ($isOpsUser) {
            $activeStatuses = ['active', 'in_progress', 'scheduled'];

            $totalActiveRentals = Rental::query()
                ->whereIn('status', $activeStatuses)
                ->count();

            $activeIoTRentals = Rental::query()
                ->join('containers', 'containers.id', '=', 'rentals.container_id')
                ->whereIn('rentals.status', $activeStatuses)
                ->where('containers.iot_active', true)
                ->count();

            $portsDistribution = DB::table('containers')
                ->join('ports', 'ports.id', '=', 'containers.current_port_id')
                ->select([
                    'containers.current_port_id',
                    'ports.name as port_name',
                ])
                ->selectRaw('COUNT(*) as total')
                ->where('containers.iot_active', true)
                ->groupBy('containers.current_port_id', 'ports.name')
                ->orderByDesc('total')
                ->limit(6)
                ->get();

            $opsSummary = [
                'active_iot_rentals' => $activeIoTRentals,
                'total_active_rentals' => $totalActiveRentals,
                'iot_share_percent' => $totalActiveRentals > 0
                    ? round($activeIoTRentals / $totalActiveRentals * 100, 1)
                    : 0.0,
                'ports_distribution' => $portsDistribution->map(static fn ($row) => [
                    'port_id' => $row->current_port_id,
                    'port_name' => $row->port_name,
                    'total' => (int) $row->total,
                ])->values(),
            ];
        }

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
        $isOpsUser = in_array((string) ($user->role ?? ''), ['admin', 'operator', 'ops'], true);

        if (! $isOpsUser && (int) $rental->user_id !== (int) $user->id) {
            abort(403);
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
        $isOpsUser = in_array((string) ($user->role ?? ''), ['admin', 'operator', 'ops'], true);

        if (! $isOpsUser && (int) $rental->user_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validated();
        $nextStatus = (string) $validated['status'];
        $currentStatus = (string) $rental->status;

        $adminOnlyStatuses = ['approved', 'rejected', 'scheduled', 'in_progress', 'completed'];
        if (! $isOpsUser && in_array($nextStatus, $adminOnlyStatuses, true)) {
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
        DB::transaction(function () use ($request, $rental) {
            ActivityLogService::log(
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
        ActivityLogService::log(
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
            ->exists();

        if ($exists) {
            return;
        }

        Notification::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false,
        ]);
    }
}
