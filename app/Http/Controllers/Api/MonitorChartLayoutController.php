<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\VerifiesRentalIsIotEligible;
use App\Http\Controllers\Controller;
use App\Models\MonitorChartLayout;
use App\Models\Rental;
use App\Services\TelemetryAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonitorChartLayoutController extends Controller
{
    use VerifiesRentalIsIotEligible;

    public function index(Request $request, ?Rental $rental = null): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        if ($rental) {
            $this->authorizeRental($request, $rental);
            $this->verifyRentalCanAccessIotMonitor($rental);
        }

        $query = MonitorChartLayout::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name');

        if ($rental) {
            $query->where(function ($q) use ($rental) {
                $q->where('rental_id', $rental->id)->orWhereNull('rental_id');
            });
        } else {
            $query->whereNull('rental_id');
        }

        $layouts = $query->get(['id', 'name', 'rental_id', 'is_default', 'config', 'created_at']);

        return response()->json([
            'data' => $layouts->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'rental_id' => $l->rental_id,
                'is_default' => $l->is_default,
                'config' => $l->config,
                'created_at' => $l->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request, ?Rental $rental = null): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_default' => ['sometimes', 'boolean'],
            'config' => ['required', 'array'],
        ]);

        if ($rental) {
            $this->authorizeRental($request, $rental);
            $this->verifyRentalCanAccessIotMonitor($rental);
        }

        if (! empty($validated['is_default'])) {
            MonitorChartLayout::query()
                ->where('user_id', $user->id)
                ->where('rental_id', $rental?->id)
                ->update(['is_default' => false]);
        }

        $layout = MonitorChartLayout::query()->create([
            'user_id' => $user->id,
            'rental_id' => $rental?->id,
            'name' => $validated['name'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'config' => $validated['config'],
        ]);

        return response()->json(['data' => $layout], 201);
    }

    public function show(Request $request, MonitorChartLayout $layout): JsonResponse
    {
        abort_if((int) $layout->user_id !== (int) $request->user()?->id, 403);

        return response()->json([
            'data' => [
                'id' => $layout->id,
                'name' => $layout->name,
                'rental_id' => $layout->rental_id,
                'is_default' => $layout->is_default,
                'config' => $layout->config,
                'created_at' => $layout->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request, MonitorChartLayout $layout): JsonResponse
    {
        abort_if((int) $layout->user_id !== (int) $request->user()?->id, 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'is_default' => ['sometimes', 'boolean'],
            'config' => ['sometimes', 'array'],
        ]);

        if (! empty($validated['is_default'])) {
            MonitorChartLayout::query()
                ->where('user_id', $layout->user_id)
                ->where('rental_id', $layout->rental_id)
                ->where('id', '!=', $layout->id)
                ->update(['is_default' => false]);
        }

        $layout->update(array_filter($validated));

        return response()->json(['data' => $layout->fresh()]);
    }

    public function destroy(Request $request, MonitorChartLayout $layout): JsonResponse
    {
        abort_if((int) $layout->user_id !== (int) $request->user()?->id, 403);
        $layout->delete();

        return response()->json(['ok' => true]);
    }

    public function exportCsv(Request $request, Rental $rental, TelemetryAnalyticsService $analytics): StreamedResponse
    {
        $this->authorizeRental($request, $rental);
        $this->verifyRentalCanAccessIotMonitor($rental);
        $container = $rental->container;
        abort_if($container === null, 404, 'No container assigned');

        $from = $request->query('from') ? \Carbon\Carbon::parse($request->query('from')) : now()->subHours(24);
        $to = $request->query('to') ? \Carbon\Carbon::parse($request->query('to')) : now();
        $sensorKeys = $request->query('sensors') ? explode(',', $request->query('sensors')) : null;

        $result = $analytics->historical(
            (int) $container->id,
            $from,
            $to,
            $sensorKeys,
            null,
            'point',
            (int) $rental->id,
            (int) $rental->user_id
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="iot-telemetry-'.now()->format('Y-m-d-His').'.csv"',
        ];

        return response()->stream(function () use ($result) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['timestamp', 'sensor_key', 'value', 'anomaly']);

            foreach ($result['series'] ?? [] as $sensorKey => $points) {
                foreach ($points as $p) {
                    fputcsv($out, [$p['timestamp'], $sensorKey, $p['value'], ($p['anomaly'] ?? false) ? '1' : '0']);
                }
            }
            fclose($out);
        }, 200, $headers);
    }

    protected function authorizeRental(Request $request, Rental $rental): void
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $isOps = in_array((string) ($user->role ?? ''), ['admin', 'operator', 'ops'], true);
        if (! $isOps && (int) $rental->user_id !== (int) $user->id) {
            abort(403);
        }
    }
}
