<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Rental;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RentalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return redirect()->route('services');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return redirect()->route('services');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'container_id' => ['required', 'integer', 'exists:containers,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $rental = DB::transaction(function () use ($request, $validated) {
            $created = Rental::query()->create([
                'user_id' => $request->user()->id,
                'container_id' => $validated['container_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'price' => $validated['price'] ?? 0,
                'status' => 'scheduled',
                'payment_status' => 'unpaid',
                'description' => $validated['description'] ?? null,
            ]);

            $this->logRentalActivity((int) $request->user()->id, 'created', $created);
            $this->createNotification(
                (int) $request->user()->id,
                "New rental #{$created->id}",
                'Your rental request has been created and is waiting for processing.',
                'info'
            );

            return $created;
        });

        return redirect()->route('dashboard')->with('status', "Rental #{$rental->id} created.");
    }

    /**
     * Display the specified resource.
     */
    public function show(Rental $rental)
    {
        return redirect()->route('dashboard');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Rental $rental)
    {
        return redirect()->route('dashboard');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'end_date' => ['nullable', 'date'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $rental, $validated) {
            $oldValues = [
                'status' => $rental->status,
                'payment_status' => $rental->payment_status,
            ];

            $rental->fill($validated);
            $rental->save();

            $this->logRentalActivity((int) $request->user()->id, 'updated', $rental, $oldValues);

            if (isset($validated['payment_status']) && in_array($validated['payment_status'], ['failed', 'unpaid'], true)) {
                $this->createNotification(
                    (int) $request->user()->id,
                    "Payment attention for rental #{$rental->id}",
                    'Please review payment status to avoid delays in rental operations.',
                    'warning'
                );
            }

            if (isset($validated['status']) && in_array($validated['status'], ['completed', 'active', 'in_progress'], true)) {
                $this->createNotification(
                    (int) $request->user()->id,
                    "Rental status updated #{$rental->id}",
                    "Rental status is now '{$validated['status']}'.",
                    'info'
                );
            }
        });

        return redirect()->route('dashboard')->with('status', "Rental #{$rental->id} updated.");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Rental $rental): RedirectResponse
    {
        DB::transaction(function () use ($rental) {
            ActivityLog::query()->create([
                'user_id' => $rental->user_id,
                'action' => 'deleted',
                'model_name' => 'Rental',
                'model_id' => $rental->id,
                'old_values' => ['status' => $rental->status],
                'new_values' => null,
                'created_at' => now(),
            ]);

            $rental->delete();
        });

        return redirect()->route('dashboard')->with('status', 'Rental deleted.');
    }

    private function logRentalActivity(int $userId, string $action, Rental $rental, ?array $oldValues = null): void
    {
        ActivityLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'model_name' => 'Rental',
            'model_id' => $rental->id,
            'old_values' => $oldValues,
            'new_values' => [
                'status' => $rental->status,
                'payment_status' => $rental->payment_status,
            ],
            'created_at' => now(),
        ]);
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
