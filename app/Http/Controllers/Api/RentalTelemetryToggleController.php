<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\VerifiesRentalIsIotEligible;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RentalTelemetryToggleController extends Controller
{
    use VerifiesRentalIsIotEligible;

    /**
     * Flip rental telemetry sleep mode (pause/resume simulated or ingested sensor stream).
     */
    public function toggle(Request $request, Rental $rental): JsonResponse
    {
        $this->authorizeRental($request, $rental);
        $this->verifyRentalCanAccessIotMonitor($rental);

        $rental->is_telemetry_active = ! (bool) $rental->is_telemetry_active;
        $rental->save();

        return response()->json([
            'is_telemetry_active' => (bool) $rental->is_telemetry_active,
        ]);
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
