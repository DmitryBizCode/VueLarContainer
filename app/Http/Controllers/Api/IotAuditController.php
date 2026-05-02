<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\VerifiesRentalIsIotEligible;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\IotAuditChainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IotAuditController extends Controller
{
    use VerifiesRentalIsIotEligible;

    public function index(Request $request, Rental $rental, IotAuditChainService $audit): JsonResponse
    {
        $this->authorizeRental($request, $rental);
        $this->verifyRentalCanAccessIotMonitor($rental);

        $container = $rental->container;
        abort_if($container === null, 404, 'No container assigned');

        $limit = min(100, max(10, (int) $request->query('limit', 50)));
        $beforeId = $request->query('before_id') ? (int) $request->query('before_id') : null;

        $events = $audit->forRental((int) $rental->id, (int) $container->id, $limit, $beforeId);

        $data = $events->map(fn ($e) => [
            'id' => $e->id,
            'sequence' => $e->sequence,
            'event_type' => $e->event_type,
            'payload' => $e->payload,
            'prev_hash' => $e->prev_hash,
            'row_hash' => $e->row_hash,
            'created_at' => $e->created_at->toIso8601String(),
            'user' => $e->user ? [
                'id' => $e->user->id,
                'name' => trim(($e->user->first_name ?? '').' '.($e->user->last_name ?? '')),
            ] : null,
        ]);

        return response()->json(['data' => $data]);
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
