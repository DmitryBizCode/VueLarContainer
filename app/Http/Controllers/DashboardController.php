<?php

namespace App\Http\Controllers;

use App\Support\FinanceStatusGroups;
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
        $txGroups = FinanceStatusGroups::transactionGroups();

        $profileChecklist = $this->buildProfileChecklist($user);

        $profileCompletion = (int) round(
            collect($profileChecklist)->filter()->count() / count($profileChecklist) * 100
        );

        $this->persistCriticalNotifications((int) $user->id);
        $this->persistDerivedNotifications((int) $user->id);

        $paidTxSub = DB::table('transactions')
            ->select('rental_id')
            ->whereRaw("LOWER(status) IN ('".implode("','", $txGroups['success'])."')")
            ->distinct();

        $now = Carbon::now();

        $stats = [
            'activeRentals' => DB::table('rentals')
                ->leftJoinSub($paidTxSub, 'paid_tx', 'paid_tx.rental_id', '=', 'rentals.id')
                ->where('rentals.user_id', $user->id)
                ->whereIn('rentals.status', ['active', 'in_progress', 'scheduled', 'approved'])
                ->where(function ($q) use ($now) {
                    $q->whereNull('rentals.end_date')
                        ->orWhere('rentals.end_date', '>=', $now);
                })
                ->where(function ($q) {
                    $q->whereRaw('LOWER(rentals.payment_status) = ?', ['paid'])
                        ->orWhereNotNull('rentals.payment_approved_at')
                        ->orWhereNotNull('paid_tx.rental_id');
                })
                ->count(),
            'completedRentals' => DB::table('rentals')
                ->leftJoinSub($paidTxSub, 'paid_tx', 'paid_tx.rental_id', '=', 'rentals.id')
                ->where('rentals.user_id', $user->id)
                ->where(function ($q) use ($now) {
                    $q->where('rentals.status', 'completed')
                        ->orWhere(function ($inner) use ($now) {
                            $inner->whereNotNull('rentals.end_date')
                                ->where('rentals.end_date', '<', $now)
                                ->whereIn('rentals.status', ['approved', 'scheduled', 'in_progress', 'active']);
                        });
                })
                ->where(function ($q) {
                    $q->whereRaw('LOWER(rentals.payment_status) = ?', ['paid'])
                        ->orWhereNotNull('rentals.payment_approved_at')
                        ->orWhereNotNull('paid_tx.rental_id');
                })
                ->count(),
            'unreadNotifications' => (int) DB::table('notifications')
                ->where('user_id', $user->id)
                ->where('is_read', false)
                ->count(),
            'recentActivityCount' => DB::table('activity_logs')
                ->where('user_id', $user->id)
                ->count(),
        ];

        $transactionsByStatus = DB::table('transactions')
            ->join('rentals', 'rentals.id', '=', 'transactions.rental_id')
            ->where('rentals.user_id', $user->id)
            ->selectRaw('LOWER(COALESCE(transactions.status, \'unknown\')) as status, COUNT(*) as count, COALESCE(SUM(transactions.amount), 0) as amount_sum')
            ->groupBy(DB::raw("LOWER(COALESCE(transactions.status, 'unknown'))"))
            ->orderBy('status')
            ->get()
            ->mapWithKeys(fn ($r) => [
                (string) $r->status => [
                    'count' => (int) $r->count,
                    'amount_sum' => (float) $r->amount_sum,
                ],
            ])
            ->all();

        $rentalsByStatus = DB::table('rentals')
            ->where('user_id', $user->id)
            ->selectRaw('LOWER(COALESCE(status, \'unknown\')) as status, COUNT(*) as count, COALESCE(SUM(price), 0) as price_sum')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->mapWithKeys(fn ($r) => [
                (string) $r->status => [
                    'count' => (int) $r->count,
                    'price_sum' => (float) $r->price_sum,
                ],
            ])
            ->all();

        $rentalsByPaymentStatus = DB::table('rentals')
            ->where('user_id', $user->id)
            ->selectRaw('LOWER(COALESCE(payment_status, \'unknown\')) as payment_status, COUNT(*) as count, COALESCE(SUM(price), 0) as price_sum')
            ->groupBy('payment_status')
            ->orderBy('payment_status')
            ->get()
            ->mapWithKeys(fn ($r) => [
                (string) $r->payment_status => [
                    'count' => (int) $r->count,
                    'price_sum' => (float) $r->price_sum,
                ],
            ])
            ->all();

        $rejectedApproval = [
            'count' => (int) ($rentalsByPaymentStatus['rejected_by_approval']['count'] ?? 0),
            'lostRevenuePriceSum' => (float) ($rentalsByPaymentStatus['rejected_by_approval']['price_sum'] ?? 0),
            'txAmountSum' => (float) DB::table('transactions')
                ->join('rentals', 'rentals.id', '=', 'transactions.rental_id')
                ->where('rentals.user_id', $user->id)
                ->whereRaw('LOWER(rentals.payment_status) = ?', ['rejected_by_approval'])
                ->sum('transactions.amount'),
        ];

        $financialOverview = DB::table('transactions')
            ->join('rentals', 'rentals.id', '=', 'transactions.rental_id')
            ->where('rentals.user_id', $user->id)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['success'])."') THEN transactions.amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['pending'])."') THEN transactions.amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN LOWER(transactions.status) IN ('".implode("','", $txGroups['failure'])."') THEN transactions.amount ELSE 0 END), 0) as failed_amount,
                COUNT(CASE WHEN LOWER(transactions.status) IN ('pending','processing') THEN 1 END) as pending_count,
                COUNT(CASE WHEN LOWER(transactions.status) = 'failed' THEN 1 END) as failed_count,
                MAX(transactions.transaction_date) as last_transaction_at
            ")
            ->first();

        $lastRejectedApprovalAt = DB::table('rentals')
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(payment_status) = ?', ['rejected_by_approval'])
            ->max('updated_at');

        $lastTransactionAt = $financialOverview->last_transaction_at ?? null;
        if ($lastRejectedApprovalAt !== null) {
            $lastTransactionAt = $lastTransactionAt === null
                ? $lastRejectedApprovalAt
                : (strtotime((string) $lastRejectedApprovalAt) > strtotime((string) $lastTransactionAt) ? $lastRejectedApprovalAt : $lastTransactionAt);
        }

        $syntheticPendingApproval = DB::table('rentals')
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(status) = ?', ['pending_approval'])
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('transactions')
                    ->whereColumn('transactions.rental_id', 'rentals.id');
            });
        $syntheticPendingApprovalAmount = (float) $syntheticPendingApproval->sum('price');
        $syntheticPendingApprovalCount = (int) $syntheticPendingApproval->count();

        $shipmentIdsForUser = DB::table('shipment_items')
            ->join('rentals', 'rentals.id', '=', 'shipment_items.rental_id')
            ->where('rentals.user_id', $user->id)
            ->distinct()
            ->pluck('shipment_items.shipment_id')
            ->filter()
            ->values();

        $now = Carbon::now();
        $shipmentHealthStatuses = ['scheduled', 'in_progress', 'in_transit'];

        $shipmentOverview = DB::table('shipments')
            ->whereIn('shipments.id', $shipmentIdsForUser)
            ->selectRaw("
                COUNT(CASE WHEN LOWER(shipments.status) IN ('scheduled','in_progress','in_transit') THEN 1 END) as in_transit_count,
                COUNT(CASE WHEN LOWER(shipments.status) IN ('scheduled','in_progress','in_transit') AND shipments.arrival_date BETWEEN ? AND ? AND shipments.actual_arrival_date IS NULL THEN 1 END) as upcoming_arrivals_count,
                COUNT(CASE WHEN LOWER(shipments.status) IN ('scheduled','in_progress','in_transit') AND shipments.arrival_date < ? AND shipments.actual_arrival_date IS NULL THEN 1 END) as delayed_count,
                COUNT(CASE WHEN shipments.actual_arrival_date BETWEEN ? AND ? THEN 1 END) as arrived_this_week_count
            ", [
                $now,
                $now->copy()->addDays(7),
                $now,
                $now->copy()->subDays(7),
                $now,
            ])
            ->first();

        $containerIdsForUser = DB::table('rentals')
            ->where('user_id', $user->id)
            ->distinct()
            ->pluck('container_id')
            ->filter()
            ->values();

        $incidentOverview = DB::table('incidents')
            ->where(function ($q) use ($shipmentIdsForUser, $containerIdsForUser) {
                if ($shipmentIdsForUser->isNotEmpty()) {
                    $q->whereIn('incidents.shipment_id', $shipmentIdsForUser);
                }
                if ($containerIdsForUser->isNotEmpty()) {
                    $q->orWhereIn('incidents.container_id', $containerIdsForUser);
                }
            })
            ->selectRaw("
                COUNT(CASE WHEN incidents.resolved_at IS NULL THEN 1 END) as open_count,
                COUNT(CASE WHEN incidents.resolved_at IS NULL AND LOWER(incidents.severity) IN ('high','critical') THEN 1 END) as high_severity_open_count
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
            ->whereBetween('start_date', [$now, $now->copy()->addDays(10)])
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
            ->whereBetween('end_date', [$now, $now->copy()->addDays(10)])
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

        $upcomingArrivals = DB::table('shipments')
            ->join('shipment_items', 'shipment_items.shipment_id', '=', 'shipments.id')
            ->join('rentals', 'rentals.id', '=', 'shipment_items.rental_id')
            ->where('rentals.user_id', $user->id)
            ->whereNull('shipments.actual_arrival_date')
            ->whereNotNull('shipments.arrival_date')
            ->whereBetween('shipments.arrival_date', [$now, $now->copy()->addDays(10)])
            ->orderBy('shipments.arrival_date')
            ->limit(3)
            ->get([
                'shipments.id as shipment_id',
                'shipments.arrival_date',
            ])
            ->map(fn ($row) => [
                'id' => 'arrival-'.$row->shipment_id,
                'type' => 'arrival',
                'title' => 'Scheduled arrival for shipment #'.$row->shipment_id,
                'date' => $row->arrival_date,
            ]);

        $upcomingPortOpsDone = DB::table('shipments')
            ->join('shipment_items', 'shipment_items.shipment_id', '=', 'shipments.id')
            ->join('rentals', 'rentals.id', '=', 'shipment_items.rental_id')
            ->where('rentals.user_id', $user->id)
            ->whereNotNull('shipments.port_operations_until')
            ->whereBetween('shipments.port_operations_until', [$now, $now->copy()->addDays(10)])
            ->orderBy('shipments.port_operations_until')
            ->limit(3)
            ->get([
                'shipments.id as shipment_id',
                'shipments.port_operations_until',
            ])
            ->map(fn ($row) => [
                'id' => 'portops-'.$row->shipment_id,
                'type' => 'port_ops',
                'title' => 'Port operations complete for shipment #'.$row->shipment_id,
                'date' => $row->port_operations_until,
            ]);

        $upcomingMilestones = collect()
            ->concat($upcomingStarts)
            ->concat($upcomingPayments)
            ->concat($upcomingArrivals)
            ->concat($upcomingPortOpsDone)
            ->sortBy('date')
            ->take(5)
            ->values();

        $latestPersistedNotifications = DB::table('notifications')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(6)
            ->select(['id', 'title', 'message', 'type', 'is_read', 'created_at'])
            ->get();

        $latestNotifications = collect($latestPersistedNotifications)->values();

        $recentRental = DB::table('rentals')
            ->join('containers', 'containers.id', '=', 'rentals.container_id')
            ->leftJoin('shipment_items', 'shipment_items.rental_id', '=', 'rentals.id')
            ->leftJoin('shipments', 'shipments.id', '=', 'shipment_items.shipment_id')
            ->leftJoin('routes', 'routes.id', '=', 'shipments.route_id')
            ->leftJoin('ports as origin_ports', 'origin_ports.id', '=', 'routes.origin_port_id')
            ->leftJoin('ports as destination_ports', 'destination_ports.id', '=', 'routes.destination_port_id')
            ->where('rentals.user_id', $user->id)
            ->leftJoinSub($paidTxSub, 'paid_tx', 'paid_tx.rental_id', '=', 'rentals.id')
            ->where(function ($q) {
                $q->whereRaw('LOWER(rentals.payment_status) = ?', ['paid'])
                    ->orWhereNotNull('rentals.payment_approved_at')
                    ->orWhereNotNull('paid_tx.rental_id');
            })
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
                'pendingAmount' => (float) ($financialOverview->pending_amount ?? 0) + $syntheticPendingApprovalAmount,
                'failedAmount' => (float) (($financialOverview->failed_amount ?? 0) + $rejectedApproval['lostRevenuePriceSum']),
                'pendingCount' => (int) ($financialOverview->pending_count ?? 0) + $syntheticPendingApprovalCount,
                'failedCount' => (int) (($financialOverview->failed_count ?? 0) + $rejectedApproval['count']),
                'lastTransactionAt' => $lastTransactionAt,
            ],
            'transactionsByStatus' => $transactionsByStatus,
            'rentalsByStatus' => $rentalsByStatus,
            'rentalsByPaymentStatus' => $rentalsByPaymentStatus,
            'rejectedApproval' => $rejectedApproval,
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

    private function persistDerivedNotifications(int $userId): void
    {
        $now = Carbon::now();

        $newRental = DB::table('rentals')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $now->copy()->subDays(2))
            ->orderByDesc('created_at')
            ->select(['id', 'created_at'])
            ->first();

        if ($newRental) {
            $this->storeNotificationIfMissing(
                $userId,
                'New rental request submitted',
                "Rental #{$newRental->id} is created and waiting for next status updates.",
                'info'
            );
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
            $this->storeNotificationIfMissing(
                $userId,
                'Upcoming rental start',
                'Rental #'.$upcomingRental->id.' starts on '.Carbon::parse($upcomingRental->start_date)->format('d M Y').'.',
                'warning'
            );
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
            $this->storeNotificationIfMissing(
                $userId,
                'Shipment status update',
                'Rental #'.$inTransitShipment->rental_id.' shipment is currently '.$inTransitShipment->status.'.',
                'info'
            );
        }
    }
}
