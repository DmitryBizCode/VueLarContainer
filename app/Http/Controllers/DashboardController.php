<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user()->loadMissing('country');

        $profileChecklist = $this->buildProfileChecklist($user);

        $profileCompletion = (int) round(
            collect($profileChecklist)->filter()->count() / count($profileChecklist) * 100
        );

        $this->persistCriticalNotifications((int) $user->id);

        $stats = [
            'activeRentals' => DB::table('rentals')
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'in_progress', 'scheduled'])
                ->count(),
            'completedRentals' => DB::table('rentals')
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->count(),
            'unreadNotifications' => DB::table('notifications')
                ->where('user_id', $user->id)
                ->where('is_read', false)
                ->count(),
            'recentActivityCount' => DB::table('activity_logs')
                ->where('user_id', $user->id)
                ->count(),
        ];

        $financialOverview = DB::table('transactions')
            ->join('rentals', 'rentals.id', '=', 'transactions.rental_id')
            ->where('rentals.user_id', $user->id)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN LOWER(transactions.status) IN ('paid','completed','succeeded','success') THEN transactions.amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN LOWER(transactions.status) IN ('pending','processing') THEN transactions.amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN LOWER(transactions.status) = 'failed' THEN transactions.amount ELSE 0 END), 0) as failed_amount,
                COUNT(CASE WHEN LOWER(transactions.status) IN ('pending','processing') THEN 1 END) as pending_count,
                COUNT(CASE WHEN LOWER(transactions.status) = 'failed' THEN 1 END) as failed_count,
                MAX(transactions.transaction_date) as last_transaction_at
            ")
            ->first();

        $shipmentOverview = DB::table('shipments')
            ->join('shipment_items', 'shipment_items.shipment_id', '=', 'shipments.id')
            ->join('rentals', 'rentals.id', '=', 'shipment_items.rental_id')
            ->where('rentals.user_id', $user->id)
            ->selectRaw("
                COUNT(DISTINCT CASE WHEN LOWER(shipments.status) IN ('scheduled','in_progress','in_transit') THEN shipments.id END) as in_transit_count,
                COUNT(DISTINCT CASE WHEN shipments.arrival_date BETWEEN ? AND ? AND shipments.actual_arrival_date IS NULL THEN shipments.id END) as upcoming_arrivals_count,
                COUNT(DISTINCT CASE WHEN shipments.arrival_date < ? AND shipments.actual_arrival_date IS NULL THEN shipments.id END) as delayed_count,
                COUNT(DISTINCT CASE WHEN shipments.actual_arrival_date >= ? THEN shipments.id END) as arrived_this_week_count
            ", [
                $now = Carbon::now(),
                $now->copy()->addDays(7),
                $now,
                $now->copy()->subDays(7),
            ])
            ->first();

        $incidentOverview = DB::table('incidents')
            ->leftJoin('shipment_items as si_sh', 'si_sh.shipment_id', '=', 'incidents.shipment_id')
            ->leftJoin('rentals as rental_by_sh', 'rental_by_sh.id', '=', 'si_sh.rental_id')
            ->leftJoin('rentals as rental_by_ct', 'rental_by_ct.container_id', '=', 'incidents.container_id')
            ->where(function ($query) use ($user) {
                $query
                    ->where('rental_by_sh.user_id', $user->id)
                    ->orWhere('rental_by_ct.user_id', $user->id);
            })
            ->selectRaw("
                COUNT(DISTINCT CASE WHEN incidents.resolved_at IS NULL THEN incidents.id END) as open_count,
                COUNT(DISTINCT CASE WHEN incidents.resolved_at IS NULL AND LOWER(incidents.severity) IN ('high','critical') THEN incidents.id END) as high_severity_open_count
            ")
            ->first();

        $topRoutes = DB::table('shipments')
            ->join('shipment_items', 'shipment_items.shipment_id', '=', 'shipments.id')
            ->join('rentals', 'rentals.id', '=', 'shipment_items.rental_id')
            ->join('routes', 'routes.id', '=', 'shipments.route_id')
            ->leftJoin('ports as origin_ports', 'origin_ports.id', '=', 'routes.origin_port_id')
            ->leftJoin('ports as destination_ports', 'destination_ports.id', '=', 'routes.destination_port_id')
            ->where('rentals.user_id', $user->id)
            ->groupBy('origin_ports.name', 'destination_ports.name')
            ->orderByDesc(DB::raw('COUNT(DISTINCT shipments.id)'))
            ->selectRaw('origin_ports.name as origin_port_name, destination_ports.name as destination_port_name, COUNT(DISTINCT shipments.id) as shipments_count')
            ->limit(3)
            ->get();

        $upcomingStarts = DB::table('rentals')
            ->where('user_id', $user->id)
            ->whereNotNull('start_date')
            ->whereBetween('start_date', [Carbon::now(), Carbon::now()->copy()->addDays(10)])
            ->select(['id', 'start_date'])
            ->orderBy('start_date')
            ->limit(3)
            ->get()
            ->map(fn ($row) => [
                'id' => 'start-'.$row->id,
                'type' => 'start',
                'title' => 'Rental #'.$row->id.' starts',
                'date' => $row->start_date,
            ]);

        $upcomingPayments = DB::table('rentals')
            ->where('user_id', $user->id)
            ->whereIn('payment_status', ['unpaid', 'pending'])
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [Carbon::now(), Carbon::now()->copy()->addDays(10)])
            ->select(['id', 'end_date'])
            ->orderBy('end_date')
            ->limit(3)
            ->get()
            ->map(fn ($row) => [
                'id' => 'payment-'.$row->id,
                'type' => 'payment',
                'title' => 'Payment deadline for rental #'.$row->id,
                'date' => $row->end_date,
            ]);

        $upcomingMilestones = collect()
            ->concat($upcomingStarts)
            ->concat($upcomingPayments)
            ->sortBy('date')
            ->take(5)
            ->values();

        $latestPersistedNotifications = DB::table('notifications')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(6)
            ->select(['id', 'title', 'message', 'type', 'is_read', 'created_at'])
            ->get();

        $derivedNotifications = $this->buildDerivedNotifications((int) $user->id);

        $latestNotifications = collect()
            ->concat($latestPersistedNotifications)
            ->concat($derivedNotifications)
            ->sortByDesc('created_at')
            ->unique(fn ($item) => ($item->title ?? '').'|'.($item->message ?? ''))
            ->take(6)
            ->values();

        $recentRental = DB::table('rentals')
            ->join('containers', 'containers.id', '=', 'rentals.container_id')
            ->leftJoin('shipment_items', 'shipment_items.rental_id', '=', 'rentals.id')
            ->leftJoin('shipments', 'shipments.id', '=', 'shipment_items.shipment_id')
            ->leftJoin('routes', 'routes.id', '=', 'shipments.route_id')
            ->leftJoin('ports as origin_ports', 'origin_ports.id', '=', 'routes.origin_port_id')
            ->leftJoin('ports as destination_ports', 'destination_ports.id', '=', 'routes.destination_port_id')
            ->where('rentals.user_id', $user->id)
            ->orderByDesc('rentals.created_at')
            ->select([
                'rentals.id',
                'rentals.start_date',
                'rentals.end_date',
                'rentals.price',
                'rentals.status as rental_status',
                'containers.serial_number as container_serial_number',
                'containers.type as container_type',
                'containers.width',
                'containers.length',
                'containers.height',
                'containers.iot_active',
                'shipments.tracking_number',
                'shipments.status as shipment_status',
                'origin_ports.name as origin_port_name',
                'destination_ports.name as destination_port_name',
                'routes.distance as route_distance',
                'rentals.payment_status',
            ])
            ->first();

        $orderHistory = DB::table('rentals')
            ->join('containers', 'containers.id', '=', 'rentals.container_id')
            ->leftJoin('shipment_items', 'shipment_items.rental_id', '=', 'rentals.id')
            ->leftJoin('shipments', 'shipments.id', '=', 'shipment_items.shipment_id')
            ->where('rentals.user_id', $user->id)
            ->orderByDesc('rentals.created_at')
            ->limit(8)
            ->select([
                'rentals.id',
                'rentals.start_date',
                'rentals.end_date',
                'rentals.price',
                'rentals.status as rental_status',
                'rentals.payment_status',
                'containers.serial_number as container_serial_number',
                'shipments.status as shipment_status',
                'shipments.tracking_number',
            ])
            ->get();

        $recentActivities = DB::table('activity_logs')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(6)
            ->select(['id', 'action', 'model_name', 'model_id', 'created_at'])
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'profileCompletion' => $profileCompletion,
            'profileReadiness' => [
                'items' => collect($profileChecklist)->map(fn ($done, $label) => [
                    'key' => $label,
                    'label' => $label,
                    'done' => $done,
                ])->values(),
                'missingFields' => collect($profileChecklist)
                    ->filter(fn ($done) => ! $done)
                    ->keys()
                    ->values(),
            ],
            'recentRental' => $recentRental,
            'orderHistory' => $orderHistory,
            'latestNotifications' => $latestNotifications,
            'recentActivities' => $recentActivities,
            'userCountryName' => $user->country?->name,
            'financialOverview' => [
                'paidAmount' => (float) ($financialOverview->paid_amount ?? 0),
                'pendingAmount' => (float) ($financialOverview->pending_amount ?? 0),
                'failedAmount' => (float) ($financialOverview->failed_amount ?? 0),
                'pendingCount' => (int) ($financialOverview->pending_count ?? 0),
                'failedCount' => (int) ($financialOverview->failed_count ?? 0),
                'lastTransactionAt' => $financialOverview->last_transaction_at ?? null,
            ],
            'shipmentOverview' => [
                'inTransitCount' => (int) ($shipmentOverview->in_transit_count ?? 0),
                'upcomingArrivalsCount' => (int) ($shipmentOverview->upcoming_arrivals_count ?? 0),
                'delayedCount' => (int) ($shipmentOverview->delayed_count ?? 0),
                'arrivedThisWeekCount' => (int) ($shipmentOverview->arrived_this_week_count ?? 0),
            ],
            'incidentOverview' => [
                'openCount' => (int) ($incidentOverview->open_count ?? 0),
                'highSeverityOpenCount' => (int) ($incidentOverview->high_severity_open_count ?? 0),
            ],
            'topRoutes' => $topRoutes,
            'upcomingMilestones' => $upcomingMilestones,
        ]);
    }

    private function buildProfileChecklist(object $user): array
    {
        return [
            'Full name' => filled($user->first_name) && filled($user->last_name),
            'Email' => filled($user->email),
            'Email verification' => ! is_null($user->email_verified_at),
            'Phone' => filled($user->phone_number),
            'Address' => filled($user->address),
            'Country' => ! is_null($user->country_id),
            'Company' => filled($user->company_name),
        ];
    }

    private function persistCriticalNotifications(int $userId): void
    {
        $now = Carbon::now();

        $paymentDueRentals = DB::table('rentals')
            ->where('user_id', $userId)
            ->whereIn('payment_status', ['unpaid', 'pending'])
            ->whereNotNull('end_date')
            ->where('end_date', '<=', $now->copy()->addDays(3))
            ->select(['id', 'end_date'])
            ->limit(3)
            ->get();

        foreach ($paymentDueRentals as $rental) {
            $title = "Payment required for rental #{$rental->id}";
            $message = 'Please complete payment before '.Carbon::parse($rental->end_date)->format('d M Y').'.';
            $this->storeNotificationIfMissing($userId, $title, $message, 'warning');
        }

        $failedTransactions = DB::table('transactions')
            ->join('rentals', 'rentals.id', '=', 'transactions.rental_id')
            ->where('rentals.user_id', $userId)
            ->where('transactions.status', 'failed')
            ->where('transactions.updated_at', '>=', $now->copy()->subDays(14))
            ->orderByDesc('transactions.updated_at')
            ->select(['rentals.id as rental_id', 'transactions.updated_at'])
            ->limit(2)
            ->get();

        foreach ($failedTransactions as $transaction) {
            $title = "Payment failed for rental #{$transaction->rental_id}";
            $message = 'Last payment attempt failed. Please retry to avoid service interruption.';
            $this->storeNotificationIfMissing($userId, $title, $message, 'error');
        }

        $arrivalMilestones = DB::table('shipments')
            ->join('shipment_items', 'shipment_items.shipment_id', '=', 'shipments.id')
            ->join('rentals', 'rentals.id', '=', 'shipment_items.rental_id')
            ->where('rentals.user_id', $userId)
            ->whereNotNull('shipments.actual_arrival_date')
            ->where('shipments.actual_arrival_date', '>=', $now->copy()->subDays(7))
            ->select(['rentals.id as rental_id', 'shipments.actual_arrival_date'])
            ->distinct()
            ->limit(3)
            ->get();

        foreach ($arrivalMilestones as $arrival) {
            $title = "Arrival update for rental #{$arrival->rental_id}";
            $message = 'Container has arrived on '.Carbon::parse($arrival->actual_arrival_date)->format('d M Y').'.';
            $this->storeNotificationIfMissing($userId, $title, $message, 'success');
        }
    }

    private function storeNotificationIfMissing(int $userId, string $title, string $message, string $type): void
    {
        $exists = DB::table('notifications')
            ->where('user_id', $userId)
            ->where('title', $title)
            ->where('message', $message)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('notifications')->insert([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buildDerivedNotifications(int $userId): array
    {
        $now = Carbon::now();
        $notifications = [];

        $newRental = DB::table('rentals')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $now->copy()->subDays(2))
            ->orderByDesc('created_at')
            ->select(['id', 'created_at'])
            ->first();

        if ($newRental) {
            $notifications[] = (object) [
                'id' => 'derived-new-rental-'.$newRental->id,
                'title' => 'New rental request submitted',
                'message' => "Rental #{$newRental->id} is created and waiting for next status updates.",
                'type' => 'info',
                'is_read' => false,
                'created_at' => $newRental->created_at,
            ];
        }

        $upcomingRental = DB::table('rentals')
            ->where('user_id', $userId)
            ->whereNotNull('start_date')
            ->where('start_date', '>=', $now)
            ->where('start_date', '<=', $now->copy()->addDays(2))
            ->orderBy('start_date')
            ->select(['id', 'start_date'])
            ->first();

        if ($upcomingRental) {
            $notifications[] = (object) [
                'id' => 'derived-upcoming-rental-'.$upcomingRental->id,
                'title' => 'Upcoming rental start',
                'message' => 'Rental #'.$upcomingRental->id.' starts on '.Carbon::parse($upcomingRental->start_date)->format('d M Y').'.',
                'type' => 'warning',
                'is_read' => false,
                'created_at' => $upcomingRental->start_date,
            ];
        }

        $inTransitShipment = DB::table('shipments')
            ->join('shipment_items', 'shipment_items.shipment_id', '=', 'shipments.id')
            ->join('rentals', 'rentals.id', '=', 'shipment_items.rental_id')
            ->where('rentals.user_id', $userId)
            ->whereIn('shipments.status', ['scheduled', 'in_progress', 'in_transit'])
            ->orderByDesc('shipments.updated_at')
            ->select(['rentals.id as rental_id', 'shipments.status', 'shipments.updated_at'])
            ->first();

        if ($inTransitShipment) {
            $notifications[] = (object) [
                'id' => 'derived-shipment-'.$inTransitShipment->rental_id,
                'title' => 'Shipment status update',
                'message' => 'Rental #'.$inTransitShipment->rental_id.' shipment is currently '.$inTransitShipment->status.'.',
                'type' => 'info',
                'is_read' => false,
                'created_at' => $inTransitShipment->updated_at,
            ];
        }

        return $notifications;
    }
}
