<?php

namespace App\Http\Controllers\Api;

use App\DataTransferObjects\ActuatorInputDto;
use App\Http\Controllers\Concerns\VerifiesRentalIsIotEligible;
use App\Http\Controllers\Controller;
use App\Models\ContainerSimulationSnapshot;
use App\Models\Rental;
use App\Services\IotAuditChainService;
use App\Services\SimulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SimulationActuatorController extends Controller
{
    use VerifiesRentalIsIotEligible;

    public function update(Request $request, Rental $rental, SimulationService $simulation, IotAuditChainService $audit): JsonResponse
    {
        $this->authorizeRental($request, $rental);
        $this->verifyRentalIsIotEligible($rental);

        $container = $rental->container;
        abort_if($container === null, 404, 'No container assigned');

        $validated = $request->validate([
            'acStatus' => ['sometimes', 'boolean'],
            'acTemp' => ['sometimes', 'numeric', 'between:-30,45'],
            'humidifier' => ['sometimes', 'boolean'],
            'heater' => ['sometimes', 'boolean'],
            'ventilation' => ['sometimes', 'boolean'],
            'mainLight' => ['sometimes', 'boolean'],
            'irLamp' => ['sometimes', 'boolean'],
            'pump' => ['sometimes', 'boolean'],
            'doorOpen' => ['sometimes', 'boolean'],
            'freshenerOn' => ['sometimes', 'boolean'],
        ]);

        $snapshot = ContainerSimulationSnapshot::query()->firstOrNew(['container_id' => $container->id]);
        $oldActuators = $snapshot->actuators ?? ActuatorInputDto::fromArray([])->toArray();
        $merged = array_merge($oldActuators, $validated);
        $actuators = ActuatorInputDto::fromArray($merged);

        $changed = array_keys(array_diff_assoc($merged, $oldActuators));
        if ($changed !== []) {
            $audit->append(
                (int) $container->id,
                IotAuditChainService::EVENT_ACTUATOR_UPDATED,
                [
                    'source' => 'api',
                    'old' => $oldActuators,
                    'new' => $merged,
                    'changed_keys' => $changed,
                ],
                (int) $rental->id,
                $request->user()?->id
            );
        }

        $state = $simulation->tickContainer($container, $rental, $actuators, writeMetricsToDatabaseImmediately: true);

        return response()->json([
            'ok' => true,
            'sensors' => $state->toArray(),
            'actuators' => $actuators->toArray(),
        ]);
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
