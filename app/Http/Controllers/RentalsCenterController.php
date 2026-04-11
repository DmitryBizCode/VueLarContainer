<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RentalsCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'shipment_status' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $userId = (int) $request->user()->id;
        $now = Carbon::now();

        $baseOverviewQuery = DB::table('rentals')->where('user_id', $userId);

        $overview = [
            'activeCount' => (clone $baseOverviewQuery)
                ->whereIn('status', ['active', 'in_progress', 'scheduled'])
                ->count(),
            'completedCount' => (clone $baseOverviewQuery)
                ->where('status', 'completed')
                ->count(),
            'overduePaymentsCount' => (clone $baseOverviewQuery)
                ->whereIn('payment_status', ['unpaid', 'pending', 'failed'])
                ->whereNotNull('end_date')
                ->where('end_date', '<', $now)
                ->count(),
            'upcomingStartsCount' => (clone $baseOverviewQuery)
                ->whereBetween('start_date', [$now, $now->copy()->addDays(7)])
                ->count(),
        ];

        $query = DB::table('rentals')
            ->join('containers', 'containers.id', '=', 'rentals.container_id')
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
                'containers.serial_number as container_serial_number',
                'containers.type as container_type',
                'containers.iot_active as container_iot_active',
            ])
            ->selectRaw('(SELECT s.status FROM shipment_items si JOIN shipments s ON s.id = si.shipment_id WHERE si.rental_id = rentals.id ORDER BY s.updated_at DESC LIMIT 1) as shipment_status')
            ->selectRaw('(SELECT s.tracking_number FROM shipment_items si JOIN shipments s ON s.id = si.shipment_id WHERE si.rental_id = rentals.id ORDER BY s.updated_at DESC LIMIT 1) as tracking_number')
            ->selectRaw('(SELECT s.arrival_date FROM shipment_items si JOIN shipments s ON s.id = si.shipment_id WHERE si.rental_id = rentals.id ORDER BY s.updated_at DESC LIMIT 1) as shipment_arrival_date')
            ->selectRaw('(SELECT t.status FROM transactions t WHERE t.rental_id = rentals.id ORDER BY t.transaction_date DESC LIMIT 1) as last_transaction_status')
            ->selectRaw('(SELECT t.transaction_date FROM transactions t WHERE t.rental_id = rentals.id ORDER BY t.transaction_date DESC LIMIT 1) as last_transaction_date')
            ->selectRaw(
                '(CASE WHEN rentals.status IN ('.implode(',', array_fill(0, count(Rental::IOT_MONITOR_ACCESS_STATUSES), '?')).') AND containers.iot_active = true THEN 1 ELSE 0 END) as can_view_iot_monitor',
                Rental::IOT_MONITOR_ACCESS_STATUSES
            )
            ->orderByDesc('rentals.created_at');

        $this->applyFilters($query, $validated);

        $rentals = $query
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('RentalsCenter', [
            'filters' => [
                'status' => $validated['status'] ?? null,
                'payment_status' => $validated['payment_status'] ?? null,
                'shipment_status' => $validated['shipment_status'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'q' => $validated['q'] ?? null,
            ],
            'overview' => $overview,
            'rentals' => $rentals,
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
}
