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
                    ->filter(fn ($done) => !$done)
                    ->keys()
                    ->values(),
            ],
            'recentRental' => $recentRental,
            'orderHistory' => $orderHistory,
            'latestNotifications' => $latestNotifications,
            'recentActivities' => $recentActivities,
            'userCountryName' => $user->country?->name,
        ]);
    }

    private function buildProfileChecklist(object $user): array
    {
        return [
            'Full name' => filled($user->first_name) && filled($user->last_name),
            'Email' => filled($user->email),
            'Email verification' => !is_null($user->email_verified_at),
            'Phone' => filled($user->phone_number),
            'Address' => filled($user->address),
            'Country' => !is_null($user->country_id),
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
