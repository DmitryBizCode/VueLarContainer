<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRentalStatusRequest;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Rental;
use App\Services\ActivityLogService;
use App\Services\RentalShipmentProvisionerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminRentalController extends Controller
{
    private const APPROVAL_REJECT_PREFIX = 'APPROVAL_REJECTED:';

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Rental::query()
            ->with(['user', 'container', 'originPort.country', 'destinationPort.country'])
            ->orderByDesc('created_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['payment_status'])) {
            $query->where('payment_status', $validated['payment_status']);
        }
        if (! empty($validated['q'])) {
            $search = '%'.addslashes($validated['q']).'%';
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', $search)
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $search)->orWhere('first_name', 'like', $search)->orWhere('last_name', 'like', $search))
                    ->orWhereHas('container', fn ($c) => $c->where('serial_number', 'like', $search));
            });
        }

        $rentals = $query->paginate(15)->withQueryString();

        $rentals->getCollection()->transform(function (Rental $r) {
            return [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'customer' => trim(($r->user?->first_name ?? '').' '.($r->user?->last_name ?? '')),
                'email' => $r->user?->email,
                'container_serial' => $r->container?->serial_number,
                'container_id' => $r->container_id,
                'origin' => $r->originPort?->name,
                'destination' => $r->destinationPort?->name,
                'start_date' => $r->start_date?->toISOString(),
                'end_date' => $r->end_date?->toISOString(),
                'price' => (float) $r->price,
                'status' => $r->status,
                'payment_status' => $r->payment_status,
                'created_at' => $r->created_at?->toISOString(),
            ];
        });

        return Inertia::render('Admin/Rentals/Index', [
            'filters' => [
                'status' => $validated['status'] ?? null,
                'payment_status' => $validated['payment_status'] ?? null,
                'q' => $validated['q'] ?? null,
            ],
            'rentals' => $rentals,
            'statusOptions' => ['draft', 'pending_approval', 'approved', 'rejected', 'scheduled', 'in_progress', 'completed', 'cancelled'],
            'paymentStatusOptions' => ['pending', 'paid', 'unpaid', 'failed', 'rejected_by_approval'],
        ]);
    }

    public function approvals(Request $request): Response
    {
        $filter = $request->validate(['tab' => ['nullable', 'string', 'in:pending,approved,rejected']])['tab'] ?? 'pending';

        $statusFilter = match ($filter) {
            'approved' => ['approved'],
            'rejected' => ['rejected'],
            default => ['pending_approval'],
        };

        $query = Rental::query()
            ->with([
                'user',
                'container.owner',
                'container.currentPort.country',
                'route.originPort',
                'route.destinationPort',
                'originPort.country',
                'destinationPort.country',
                'reviewer:id,first_name,last_name,email',
            ])
            ->whereIn('status', $statusFilter)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $rentalIds = $query->pluck('id')->all();
        $approvalLogs = ActivityLog::query()
            ->with('user:id,first_name,last_name,email')
            ->where('model_name', 'Rental')
            ->whereIn('model_id', $rentalIds)
            ->where(function ($q) {
                $q->where('action', 'like', 'status_changed_to_%')
                    ->orWhere('action', 'submitted_for_approval');
            })
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('model_id');

        $query->getCollection()->transform(function (Rental $r) use ($approvalLogs) {
            $logs = collect($approvalLogs->get($r->id, []))->map(fn (ActivityLog $log) => [
                'created_at' => $log->created_at?->toISOString(),
                'action' => $log->action,
                'user_name' => trim(($log->user?->first_name ?? '').' '.($log->user?->last_name ?? '')) ?: $log->user?->email ?? '—',
                'description' => $log->description,
            ])->values()->all();

            return [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'customer' => trim(($r->user?->first_name ?? '').' '.($r->user?->last_name ?? '')),
                'email' => $r->user?->email,
                'container' => $r->container ? [
                    'id' => $r->container->id,
                    'serial_number' => $r->container->serial_number,
                    'type' => $r->container->type,
                ] : null,
                'origin_port' => $r->originPort ? ['id' => $r->originPort->id, 'name' => $r->originPort->name, 'country' => $r->originPort->country?->name] : null,
                'destination_port' => $r->destinationPort ? ['id' => $r->destinationPort->id, 'name' => $r->destinationPort->name, 'country' => $r->destinationPort->country?->name] : null,
                'start_date' => $r->start_date?->toISOString(),
                'end_date' => $r->end_date?->toISOString(),
                'price' => (float) $r->price,
                'status' => $r->status,
                'payment_status' => $r->payment_status,
                'description' => $r->description,
                'cargo_types' => $r->cargo_types,
                'contact_name' => $r->contact_name,
                'contact_phone' => $r->contact_phone,
                'created_at' => $r->created_at?->toISOString(),
                'reviewed_at' => $r->reviewed_at?->toISOString(),
                'reviewer' => $r->reviewer ? [
                    'id' => $r->reviewer->id,
                    'name' => trim($r->reviewer->first_name.' '.$r->reviewer->last_name) ?: $r->reviewer->email,
                    'email' => $r->reviewer->email,
                ] : null,
                'client' => [
                    'first_name' => $r->user?->first_name,
                    'last_name' => $r->user?->last_name,
                    'email' => $r->user?->email,
                    'company_name' => $r->user?->company_name,
                    'phone_number' => $r->user?->phone_number,
                ],
                'approval_log' => $logs,
                'rejection_reason' => $r->rejection_reason,
                'cancellation_reason' => $r->cancellation_reason,
            ];
        });

        return Inertia::render('Admin/Approvals', [
            'rentals' => $query,
            'activeTab' => $filter,
            'statusOptions' => ['approved', 'rejected'],
        ]);
    }

    public function full(Rental $rental): JsonResponse
    {
        $rental->load([
            'user.country',
            'container.owner',
            'container.currentPort.country',
            'route.originPort',
            'route.destinationPort',
            'originPort.country',
            'destinationPort.country',
            'reviewer',
        ]);

        $user = $rental->user;
        $container = $rental->container;
        $route = $rental->route;

        return response()->json([
            'id' => $rental->id,
            'user_id' => $rental->user_id,
            'container_id' => $rental->container_id,
            'route_id' => $rental->route_id,
            'origin_port_id' => $rental->origin_port_id,
            'destination_port_id' => $rental->destination_port_id,
            'start_date' => $rental->start_date?->toISOString(),
            'end_date' => $rental->end_date?->toISOString(),
            'actual_return_date' => $rental->actual_return_date?->toISOString(),
            'rental_days' => $rental->rental_days,
            'cargo_types' => $rental->cargo_types,
            'cargo_details' => $rental->cargo_details,
            'requested_weight' => $rental->requested_weight !== null ? (float) $rental->requested_weight : null,
            'cargo_volume_cbm' => $rental->cargo_volume_cbm !== null ? (float) $rental->cargo_volume_cbm : null,
            'package_count' => $rental->package_count,
            'cargo_value' => $rental->cargo_value !== null ? (float) $rental->cargo_value : null,
            'priority' => $rental->priority,
            'incoterm' => $rental->incoterm,
            'loading_type' => $rental->loading_type,
            'delivery_mode' => $rental->delivery_mode,
            'sustainability_pref' => $rental->sustainability_pref,
            'insurance_required' => (bool) $rental->insurance_required,
            'requires_customs_clearance' => (bool) $rental->requires_customs_clearance,
            'hazardous_material' => (bool) $rental->hazardous_material,
            'requires_escort' => (bool) $rental->requires_escort,
            'seal_required' => (bool) $rental->seal_required,
            'un_number' => $rental->un_number,
            'dangerous_goods_class' => $rental->dangerous_goods_class,
            'origin_customs_code' => $rental->origin_customs_code,
            'destination_customs_code' => $rental->destination_customs_code,
            'temperature_min' => $rental->temperature_min !== null ? (float) $rental->temperature_min : null,
            'temperature_max' => $rental->temperature_max !== null ? (float) $rental->temperature_max : null,
            'contact_name' => $rental->contact_name,
            'contact_phone' => $rental->contact_phone,
            'pickup_address' => $rental->pickup_address,
            'delivery_address' => $rental->delivery_address,
            'pickup_window_start' => $rental->pickup_window_start?->toISOString(),
            'pickup_window_end' => $rental->pickup_window_end?->toISOString(),
            'quote_expires_at' => $rental->quote_expires_at?->toISOString(),
            'terms_accepted' => (bool) $rental->terms_accepted,
            'special_requirements' => $rental->special_requirements,
            'estimated_distance' => $rental->estimated_distance !== null ? (float) $rental->estimated_distance : null,
            'price' => (float) $rental->price,
            'price_breakdown' => $rental->price_breakdown,
            'status' => $rental->status,
            'payment_status' => $rental->payment_status,
            'reviewed_by' => $rental->reviewed_by,
            'reviewed_at' => $rental->reviewed_at?->toISOString(),
            'rejection_reason' => $rental->rejection_reason,
            'cancellation_reason' => $rental->cancellation_reason,
            'contract_pdf' => $rental->contract_pdf,
            'description' => $rental->description,
            'created_at' => $rental->created_at?->toISOString(),
            'updated_at' => $rental->updated_at?->toISOString(),
            'user' => $user ? [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'company_name' => $user->company_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'address' => $user->address,
                'country_id' => $user->country_id,
                'country_name' => $user->country?->name,
                'account_status' => $user->account_status,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
            ] : null,
            'container' => $container ? [
                'id' => $container->id,
                'serial_number' => $container->serial_number,
                'type' => $container->type,
                'width' => (float) $container->width,
                'length' => (float) $container->length,
                'height' => (float) $container->height,
                'max_weight' => (float) $container->max_weight,
                'owner_id' => $container->owner_id,
                'owner_name' => $container->owner?->name,
                'current_port_id' => $container->current_port_id,
                'current_port_name' => $container->currentPort?->name,
            ] : null,
            'route' => $route ? [
                'id' => $route->id,
                'origin_port_id' => $route->origin_port_id,
                'destination_port_id' => $route->destination_port_id,
                'origin_name' => $route->originPort?->name,
                'destination_name' => $route->destinationPort?->name,
                'estimated_days' => $route->estimated_days,
                'distance' => (float) $route->distance,
                'route_status' => $route->route_status,
            ] : null,
            'origin_port' => $rental->originPort ? ['id' => $rental->originPort->id, 'name' => $rental->originPort->name, 'country' => $rental->originPort->country?->name] : null,
            'destination_port' => $rental->destinationPort ? ['id' => $rental->destinationPort->id, 'name' => $rental->destinationPort->name, 'country' => $rental->destinationPort->country?->name] : null,
            'reviewer' => $rental->reviewer ? ['id' => $rental->reviewer->id, 'name' => trim($rental->reviewer->first_name.' '.$rental->reviewer->last_name)] : null,
        ]);
    }

    public function show(Rental $rental): Response
    {
        $rental->load([
            'user',
            'container.owner',
            'container.currentPort.country',
            'route.originPort',
            'route.destinationPort',
            'originPort.country',
            'destinationPort.country',
        ]);

        return Inertia::render('Admin/Rentals/Show', [
            'rental' => [
                'id' => $rental->id,
                'user_id' => $rental->user_id,
                'customer' => trim(($rental->user?->first_name ?? '').' '.($rental->user?->last_name ?? '')),
                'email' => $rental->user?->email,
                'container' => $rental->container ? [
                    'id' => $rental->container->id,
                    'serial_number' => $rental->container->serial_number,
                    'type' => $rental->container->type,
                ] : null,
                'origin_port' => $rental->originPort ? ['id' => $rental->originPort->id, 'name' => $rental->originPort->name, 'country' => $rental->originPort->country?->name] : null,
                'destination_port' => $rental->destinationPort ? ['id' => $rental->destinationPort->id, 'name' => $rental->destinationPort->name, 'country' => $rental->destinationPort->country?->name] : null,
                'start_date' => $rental->start_date?->toISOString(),
                'end_date' => $rental->end_date?->toISOString(),
                'price' => (float) $rental->price,
                'status' => $rental->status,
                'payment_status' => $rental->payment_status,
                'reviewed_at' => $rental->reviewed_at?->toISOString(),
                'rejection_reason' => $rental->rejection_reason,
                'cancellation_reason' => $rental->cancellation_reason,
                'description' => $rental->description,
                'created_at' => $rental->created_at?->toISOString(),
            ],
        ]);
    }

    public function updateStatus(UpdateRentalStatusRequest $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validated();
        $nextStatus = (string) $validated['status'];
        $currentStatus = (string) $rental->status;

        if (! $this->isAllowedTransition($currentStatus, $nextStatus)) {
            return back()->withErrors(['status' => "Transition from '{$currentStatus}' to '{$nextStatus}' is not allowed."]);
        }

        DB::transaction(function () use ($request, $rental, $validated, $nextStatus, $currentStatus) {
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
            } elseif ($nextStatus === 'pending_approval') {
                $rental->reviewed_by = null;
                $rental->reviewed_at = null;
                $rental->rejection_reason = null;
                $rental->cancellation_reason = null;
            } elseif (! empty($validated['payment_status'])) {
                $rental->payment_status = $validated['payment_status'];
            }
            if ($nextStatus === 'pending_approval') {
                // reasons already cleared above
            } elseif ($nextStatus === 'rejected') {
                $rental->rejection_reason = $validated['rejection_reason'] ?? null;
                $rental->cancellation_reason = null;
                // Any reject from the approval queue should be treated as rejected-by-approval for finance reporting,
                // even if the operator does not include the legacy marker prefix in the reason text.
                if ($currentStatus === 'pending_approval' || $this->isApprovalRejectedReason((string) ($rental->rejection_reason ?? ''))) {
                    $rental->payment_status = 'rejected_by_approval';
                }
            } elseif ($nextStatus === 'cancelled') {
                $rental->cancellation_reason = $validated['cancellation_reason'] ?? null;
                $rental->rejection_reason = null;
            } else {
                $rental->rejection_reason = null;
                $rental->cancellation_reason = null;
            }
            $rental->save();

            ActivityLogService::log(
                $request->user()->id,
                "status_changed_to_{$nextStatus}",
                'Rental',
                $rental->id,
                $oldValues,
                ['status' => $rental->status, 'payment_status' => $rental->payment_status, 'reviewed_by' => $rental->reviewed_by, 'reviewed_at' => $rental->reviewed_at?->toISOString()],
                "Rental #{$rental->id} status changed to {$nextStatus} by ".trim($request->user()->first_name.' '.$request->user()->last_name),
                $request
            );
            Notification::query()->create([
                'user_id' => $rental->user_id,
                'title' => "Rental #{$rental->id} status update",
                'message' => "Rental status changed to '{$nextStatus}'.",
                'type' => $nextStatus === 'rejected' ? 'warning' : 'info',
                'is_read' => false,
            ]);
        });

        if ($nextStatus === 'approved') {
            $rental->refresh();
            try {
                app(RentalShipmentProvisionerService::class)->provisionForApprovedRental($rental);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', "Rental #{$rental->id} updated.");
    }

    private function isApprovalRejectedReason(string $reason): bool
    {
        return str_starts_with(trim($reason), self::APPROVAL_REJECT_PREFIX);
    }

    public function destroy(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user || ! \Illuminate\Support\Facades\Hash::check($validated['password'], $user->password)) {
            return back()->with('error', 'Incorrect password. Rental was not deleted.');
        }

        DB::transaction(function () use ($request, $rental) {
            ActivityLogService::log(
                $request->user()->id,
                'rental_deleted',
                'Rental',
                $rental->id,
                ['status' => $rental->status],
                null,
                "Rental #{$rental->id} deleted by admin",
                $request
            );
            $rental->delete();
        });

        return redirect()->route('admin.rentals.index')->with('status', 'Rental deleted.');
    }

    private function isAllowedTransition(string $currentStatus, string $nextStatus): bool
    {
        if ($currentStatus === $nextStatus) {
            return true;
        }

        $allowedTransitions = [
            'draft' => ['pending_approval', 'cancelled'],
            'pending_approval' => ['approved', 'rejected', 'cancelled'],
            'approved' => ['scheduled', 'in_progress', 'cancelled', 'pending_approval'],
            'scheduled' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'rejected' => ['pending_approval'],
            'completed' => [],
            'cancelled' => [],
        ];

        return in_array($nextStatus, $allowedTransitions[$currentStatus] ?? [], true);
    }
}
