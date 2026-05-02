<?php

namespace App\Console\Commands;

use App\Services\ShipmentScheduleAdvancerService;
use App\Services\SimulationService;
use Illuminate\Console\Command;

class SimulationTickCommand extends Command
{
    protected $signature = 'simulation:tick {--container= : Optional container ID}';

    protected $description = 'Advance IoT simulation, shipment schedule, and vessel statuses one tick';

    public function handle(SimulationService $simulation, ShipmentScheduleAdvancerService $schedule): int
    {
        $onlyId = $this->option('container') ? (int) $this->option('container') : null;
        $count = $simulation->tickAllIotActiveContainers($onlyId);

        // Only advance the global schedule when not targeting a single container (ad-hoc debug tick).
        $scheduleSummary = '';
        if ($onlyId === null) {
            $result = $schedule->tick();
            $scheduleSummary = " · shipments advanced={$result['advanced']} · vessels synced={$result['vessels_synced']}";
        }

        $this->info("Simulation tick completed for {$count} container(s){$scheduleSummary}.");

        return self::SUCCESS;
    }
}
