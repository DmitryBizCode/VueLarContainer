<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\VerifiesRentalIsIotEligible;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\MonitorChartsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitorChartsController extends Controller
{
    use VerifiesRentalIsIotEligible;

    public function __construct(
        private readonly MonitorChartsService $monitorCharts
    ) {}

    public function index(Request $request, Rental $rental): JsonResponse
    {
        $this->authorizeRental($request, $rental);
        $this->verifyRentalCanAccessIotMonitor($rental);

        $rental->loadMissing(['container']);

        if ($rental->container === null) {
            return response()->json(['error' => 'No container assigned'], 404);
        }

        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->input('to'))
            : CarbonImmutable::now();
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->input('from'))
            : $to->copy()->subHours(24);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $maxRange = 168;
        if ($from->diffInHours($to) > $maxRange) {
            $to = $from->addHours($maxRange);
        }

        /*
        | Live monitor polls reuse the last response's `date_to`, which quickly falls behind wall clock.
        | Metrics/buffer rows with recorded_at > stale `to` are excluded server-side — charts freeze.
        | If the window still looks "live" (lag < 72h), slide [from,to] forward to now with same span.
        */
        $now = CarbonImmutable::now();
        if ($to->lt($now->subSeconds(90))) {
            $lagHours = $to->diffInHours($now);
            if ($lagHours < 72) {
                $spanHours = max(1, min($maxRange, $from->diffInHours($to)));
                $to = $now;
                $from = $to->copy()->subHours($spanHours);
            }
        }

        $seriesMode = strtolower(trim((string) $request->input('series_mode', 'window')));
        if ($seriesMode !== 'raw_tail') {
            $seriesMode = 'window';
        }

        $charts = $this->monitorCharts->build($rental, $from, $to, $seriesMode);

        if (config('app.debug')) {
            $charts['_debug'] = [
                'container_id' => $rental->container_id,
                'rental_id' => $rental->id,
                'lessee_user_id' => $rental->user_id,
                'query_from' => $from->toIso8601String(),
                'query_to' => $to->toIso8601String(),
                'sensor_panels' => count($charts['sensors'] ?? []),
            ];
        }

        return response()
            ->json($charts)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    protected function authorizeRental(Request $request, Rental $rental): void
    {
        $user = $request->user();
        abort_if($user === null, 401);

        if ((int) $rental->user_id !== (int) $user->id) {
            abort(404);
        }
    }
}
