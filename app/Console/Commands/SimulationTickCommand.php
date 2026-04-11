<?php

namespace App\Console\Commands;

use App\Services\SimulationService;
use Illuminate\Console\Command;

class SimulationTickCommand extends Command
{
    protected $signature = 'simulation:tick {--container= : Optional container ID}';

    protected $description = 'Advance IoT simulation one tick for IoT-active containers';

    public function handle(SimulationService $simulation): int
    {
        $onlyId = $this->option('container') ? (int) $this->option('container') : null;
        $count = $simulation->tickAllIotActiveContainers($onlyId);

        $this->info("Simulation tick completed for {$count} container(s).");

        return self::SUCCESS;
    }
}
