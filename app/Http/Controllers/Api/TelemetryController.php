<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\VerifiesRentalIsIotEligible;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\TelemetryAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    use VerifiesRentalIsIotEligible;

    public function telemetry(Request $request, Rental $rental, TelemetryAnalyticsService $analytics): JsonResponse
    {
        $this->authorizeRental($request, $rental);
        $this->verifyRentalCanAccessIotMonitor($rental);

        $container = $rental->container;
        abort_if($container === null, 404, 'No container assigned');

        $payload = $analytics->latestForContainer((int) $container->id, (int) $rental->id, (int) $rental->user_id);

        return response()->json($payload);
    }

    public function analytics(Request $request, Rental $rental, TelemetryAnalyticsService $analytics): JsonResponse
    {
        $this->authorizeRental($request, $rental);
        $this->verifyRentalCanAccessIotMonitor($rental);

        $container = $rental->container;
        abort_if($container === null, 404, 'No container assigned');

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sensor_key' => ['nullable', 'array'],
            'sensor_key.*' => ['string', 'max:64'],
            'fluctuation_threshold' => ['nullable', 'numeric', 'min:0'],
            'mode' => ['nullable', 'in:point,window'],
        ]);

        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])
            : CarbonImmutable::now();
        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])
            : $to->subHours(24);

        $sensorKeys = $validated['sensor_key'] ?? null;
        $threshold = isset($validated['fluctuation_threshold'])
            ? (float) $validated['fluctuation_threshold']
            : null;
        $mode = $validated['mode'] ?? 'point';

        $result = $analytics->historical(
            (int) $container->id,
            $from,
            $to,
            $sensorKeys,
            $threshold,
            $mode,
            (int) $rental->id,
            (int) $rental->user_id
        );

        return response()->json($result);
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
