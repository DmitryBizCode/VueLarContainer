<?php

namespace App\Http\Controllers;

use App\Models\Port;
use App\Models\Rental;
use App\Models\ShipmentItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RentalsCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'string', 'in:successful,all'],
            'status' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'shipment_status' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $user = $request->user();
        $userId = (int) $user->id;
        $now = CarbonImmutable::now();

        $baseOverviewQuery = Rental::query()->where('user_id', $userId);

        $imminentDays = max(0, (int) config('logistics_map.imminent_start_horizon_days', 60));
        $latestAllowedStart = $now->addDays($imminentDays);

        // "Upcoming starts" / "Next 7 days": from local midnight today, next 7 calendar day boundaries (excludes non-actionable).
        $upcomingWindowStart = $now->startOfDay();
        $upcomingWindowEnd = $upcomingWindowStart->addDays(7);

        $overview = [
            'activeCount' => (clone $baseOverviewQuery)
                ->tap(fn (Builder $q) => $this->applyMapActiveRules($q, $now, $latestAllowedStart))
                ->count(),
            'completedCount' => (clone $baseOverviewQuery)
                ->where('status', 'completed')
                ->count(),
            'overduePaymentsCount' => (clone $baseOverviewQuery)
                ->whereNotIn('status', ['cancelled', 'rejected', 'draft', 'completed'])
                ->whereNotIn('payment_status', ['paid', 'rejected_by_approval'])
                ->whereNotNull('end_date')
                ->where('end_date', '<', $now)
                ->count(),
            'upcomingStartsCount' => (clone $baseOverviewQuery)
                ->whereNotNull('start_date')
                ->where('start_date', '>=', $upcomingWindowStart)
                ->where('start_date', '<', $upcomingWindowEnd)
                ->whereNotIn('status', ['cancelled', 'rejected', 'draft', 'completed'])
                ->count(),
        ];

        $query = Rental::query()
            ->join('containers', 'containers.id', '=', 'rentals.container_id')
            ->leftJoin('ports as origin_ports', 'origin_ports.id', '=', 'rentals.origin_port_id')
            ->leftJoin('ports as destination_ports', 'destination_ports.id', '=', 'rentals.destination_port_id')
            ->where('rentals.user_id', $userId)
            ->select([
                'rentals.id',
                'rentals.start_date',
                'rentals.end_date',
                'rentals.actual_return_date',
                'rentals.price',
                'rentals.status',
                'rentals.payment_status',
                'rentals.description',
                'rentals.rejection_reason',
                'rentals.cancellation_reason',
                'rentals.price_breakdown',
                'containers.serial_number as container_serial_number',
                'containers.type as container_type',
                'containers.iot_active as container_iot_active',
                'containers.current_status as container_operational_status',
                'origin_ports.name as origin_port_name',
                'destination_ports.name as destination_port_name',
            ])
            ->selectRaw('(SELECT s.status FROM shipment_items si JOIN shipments s ON s.id = si.shipment_id WHERE si.rental_id = rentals.id ORDER BY s.updated_at DESC LIMIT 1) as shipment_status')
            ->selectRaw('(SELECT s.tracking_number FROM shipment_items si JOIN shipments s ON s.id = si.shipment_id WHERE si.rental_id = rentals.id ORDER BY s.updated_at DESC LIMIT 1) as tracking_number')
            ->selectRaw('(SELECT s.arrival_date FROM shipment_items si JOIN shipments s ON s.id = si.shipment_id WHERE si.rental_id = rentals.id ORDER BY s.updated_at DESC LIMIT 1) as shipment_arrival_date')
            ->selectRaw('(SELECT t.status FROM transactions t WHERE t.rental_id = rentals.id ORDER BY t.transaction_date DESC LIMIT 1) as last_transaction_status')
            ->selectRaw('(SELECT t.transaction_date FROM transactions t WHERE t.rental_id = rentals.id ORDER BY t.transaction_date DESC LIMIT 1) as last_transaction_date')
            ->selectRaw(
                '(CASE WHEN rentals.status IN ('.implode(',', array_fill(0, count(Rental::IOT_MONITOR_ACCESS_STATUSES), '?')).') AND containers.iot_active = true AND (rentals.status = ? OR rentals.end_date IS NULL OR rentals.end_date >= ?) THEN 1 ELSE 0 END) as can_view_iot_monitor',
                array_merge(Rental::IOT_MONITOR_ACCESS_STATUSES, ['completed', $now])
            )
            ->orderByDesc('rentals.created_at');

        // Default list: "successful" = same gates as map/trackable rentals; "all" = every rental for this user.
        $listScope = ($validated['scope'] ?? 'successful') === 'all' ? 'all' : 'successful';
        $hasAnyFilter = ! empty($validated['status'])
            || ! empty($validated['payment_status'])
            || ! empty($validated['shipment_status'])
            || ! empty($validated['date_from'])
            || ! empty($validated['date_to'])
            || ! empty($validated['q']);
        if (! $hasAnyFilter && $listScope === 'successful') {
            $this->applyMapActiveRules($query, $now, $latestAllowedStart);
        }

        $this->applyFilters($query, $validated);

        $rentals = $query
            ->paginate(12)
            ->withQueryString();

        // Persisted proxy/transshipment segments: reuse shipments + shipment_items.rental_id.
        $rentalIdList = collect($rentals->items())->pluck('id')->map(fn ($v) => (int) $v)->values()->all();
        $segmentsByRentalId = $rentalIdList === []
            ? []
            : ShipmentItem::query()->from('shipment_items as si')
                ->join('shipments as s', 's.id', '=', 'si.shipment_id')
                ->join('routes as r', 'r.id', '=', 's.route_id')
                ->leftJoin('ports as op', 'op.id', '=', 'r.origin_port_id')
                ->leftJoin('ports as dp', 'dp.id', '=', 'r.destination_port_id')
                ->whereIn('si.rental_id', $rentalIdList)
                ->orderBy('s.leg_sequence')
                ->orderBy('s.updated_at')
                ->get([
                    'si.rental_id',
                    's.id as shipment_id',
                    's.leg_sequence',
                    's.status',
                    's.route_id',
                    's.vessel_id',
                    's.departure_date',
                    's.arrival_date',
                    's.actual_departure_date',
                    's.actual_arrival_date',
                    's.port_operations_until',
                    'op.name as origin_port_name',
                    'dp.name as destination_port_name',
                ])
                ->groupBy('rental_id')
                ->map(fn ($rows) => $rows->map(fn ($row) => [
                    'shipment_id' => (int) $row->shipment_id,
                    'leg_sequence' => (int) $row->leg_sequence,
                    'status' => (string) $row->status,
                    'route_id' => (int) $row->route_id,
                    'vessel_id' => $row->vessel_id !== null ? (int) $row->vessel_id : null,
                    'departure_date' => $row->departure_date,
                    'arrival_date' => $row->arrival_date,
                    'actual_departure_date' => $row->actual_departure_date,
                    'actual_arrival_date' => $row->actual_arrival_date,
                    'port_operations_until' => $row->port_operations_until,
                    'origin_port_name' => $row->origin_port_name,
                    'destination_port_name' => $row->destination_port_name,
                ])->values()->all())
                ->all();

        // Build a light route_summary (direct vs N-leg transshipment) for every row by resolving port names
        // against `rentals.price_breakdown.route_legs`. Keeps the Vue layer free of parsing logic.
        $legPortIds = [];
        foreach ($rentals->items() as $row) {
            $legs = $this->decodeRouteLegs($row->price_breakdown ?? null);
            foreach ($legs as $leg) {
                if (! empty($leg['origin_port_id'])) {
                    $legPortIds[(int) $leg['origin_port_id']] = true;
                }
                if (! empty($leg['destination_port_id'])) {
                    $legPortIds[(int) $leg['destination_port_id']] = true;
                }
            }
        }
        $portNameById = $legPortIds === []
            ? []
            : Port::query()->whereIn('id', array_keys($legPortIds))->pluck('name', 'id')->all();

        foreach ($rentals->items() as $row) {
            $legs = $this->decodeRouteLegs($row->price_breakdown ?? null);
            $row->route_summary = $this->buildRouteSummary($legs, $portNameById, (string) $row->origin_port_name, (string) $row->destination_port_name);
            $row->segment_summary = $segmentsByRentalId[(int) $row->id] ?? [];
            unset($row->price_breakdown);
        }

        return Inertia::render('Operations/RentalsCenter/Index', [
            'filters' => [
                'scope' => $listScope,
                'status' => $validated['status'] ?? null,
                'payment_status' => $validated['payment_status'] ?? null,
                'shipment_status' => $validated['shipment_status'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'q' => $validated['q'] ?? null,
            ],
            'overview' => $overview,
            'rentals' => $rentals,
            'is_ops_view' => false,
        ]);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $status = strtolower((string) $filters['status']);
            $query->whereRaw('LOWER(rentals.status) = ?', [$status]);
        }

        if (! empty($filters['payment_status'])) {
            $paymentStatus = strtolower((string) $filters['payment_status']);
            $query->whereRaw('LOWER(rentals.payment_status) = ?', [$paymentStatus]);
        }

        if (! empty($filters['shipment_status'])) {
            $shipmentStatus = strtolower((string) $filters['shipment_status']);
            $query->whereRaw(
                "LOWER(COALESCE((SELECT s.status FROM shipment_items si JOIN shipments s ON s.id = si.shipment_id WHERE si.rental_id = rentals.id ORDER BY s.updated_at DESC LIMIT 1), '')) = ?",
                [$shipmentStatus]
            );
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('rentals.start_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('rentals.start_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['q'])) {
            $search = mb_strtolower(trim((string) $filters['q']));

            $query->where(function (Builder $nested) use ($search) {
                $nested
                    ->orWhereRaw('CAST(rentals.id AS CHAR) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(containers.serial_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw(
                        "LOWER(COALESCE((SELECT s.tracking_number FROM shipment_items si JOIN shipments s ON s.id = si.shipment_id WHERE si.rental_id = rentals.id ORDER BY s.updated_at DESC LIMIT 1), '')) LIKE ?",
                        ["%{$search}%"]
                    );
            });
        }
    }

    private function applyMapActiveRules(Builder $query, CarbonImmutable $now, CarbonImmutable $latestAllowedStart): void
    {
        $activeRentalStatuses = ['approved', 'scheduled', 'in_progress', 'active'];

        $query
            ->whereRaw('LOWER(rentals.status) IN (?,?,?,?)', $activeRentalStatuses)
            ->where(function ($q) use ($latestAllowedStart) {
                $q->whereNull('rentals.start_date')
                    ->orWhere('rentals.start_date', '<=', $latestAllowedStart);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('rentals.end_date')
                    ->orWhere('rentals.end_date', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereIn('rentals.status', ['in_progress', 'active'])
                    ->orWhere('rentals.payment_status', 'paid');
            });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeRouteLegs(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded)) {
            return [];
        }
        $legs = $decoded['route_legs'] ?? null;

        return is_array($legs) ? array_values(array_filter($legs, 'is_array')) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $legs
     * @param  array<int, string>  $portNameById
     * @return array<string, mixed>
     */
    private function buildRouteSummary(array $legs, array $portNameById, string $originName, string $destinationName): array
    {
        if ($legs === []) {
            $label = $originName !== '' && $destinationName !== ''
                ? "{$originName} → {$destinationName}"
                : '';

            return [
                'is_multi_hop' => false,
                'leg_count' => 0,
                'label' => $label,
                'intermediate_ports' => [],
                'legs' => [],
            ];
        }

        $resolved = [];
        foreach ($legs as $leg) {
            $oid = isset($leg['origin_port_id']) ? (int) $leg['origin_port_id'] : null;
            $did = isset($leg['destination_port_id']) ? (int) $leg['destination_port_id'] : null;
            $resolved[] = [
                'route_id' => isset($leg['route_id']) ? (int) $leg['route_id'] : null,
                'origin_port_id' => $oid,
                'destination_port_id' => $did,
                'origin_name' => $oid !== null ? ($portNameById[$oid] ?? null) : null,
                'destination_name' => $did !== null ? ($portNameById[$did] ?? null) : null,
                'estimated_days' => isset($leg['estimated_days']) ? (int) $leg['estimated_days'] : null,
                'distance' => isset($leg['distance']) ? (float) $leg['distance'] : null,
            ];
        }

        $legCount = count($resolved);
        $isMultiHop = $legCount > 1;
        $firstOrigin = $resolved[0]['origin_name'] ?? ($originName !== '' ? $originName : null);
        $lastDestination = $resolved[$legCount - 1]['destination_name'] ?? ($destinationName !== '' ? $destinationName : null);

        $intermediates = [];
        for ($i = 0; $i < $legCount - 1; $i++) {
            $name = $resolved[$i]['destination_name'] ?? null;
            if ($name !== null && $name !== '') {
                $intermediates[] = $name;
            }
        }

        if ($isMultiHop) {
            $chain = array_filter(array_merge([$firstOrigin], $intermediates, [$lastDestination]));
            $label = implode(' → ', $chain)." ({$legCount} legs)";
        } else {
            $label = ($firstOrigin !== null && $lastDestination !== null)
                ? "{$firstOrigin} → {$lastDestination}"
                : '';
        }

        return [
            'is_multi_hop' => $isMultiHop,
            'leg_count' => $legCount,
            'label' => $label,
            'intermediate_ports' => $intermediates,
            'legs' => $resolved,
        ];
    }
}
