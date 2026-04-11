<?php

namespace Tests\Unit;

use App\DataTransferObjects\ActuatorInputDto;
use App\DataTransferObjects\SimulationStateDto;
use App\Services\SimulationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SimulationServiceTest extends TestCase
{
    #[Test]
    public function humidifier_increases_humidity_and_decreases_temperature(): void
    {
        $service = new SimulationService;
        $state = new SimulationStateDto(6.0, 50.0, 800.0);
        $actuators = new ActuatorInputDto(humidifier: true);

        $ref = new \ReflectionClass($service);
        $m = $ref->getMethod('processInterdependencies');
        $next = $m->invoke($service, $state, $actuators);

        $this->assertGreaterThan(50.0, $next->humidity_rh);
        $this->assertLessThan(6.0, $next->temperature_c);
    }

    #[Test]
    public function ventilation_reduces_co2(): void
    {
        $service = new SimulationService;
        $state = new SimulationStateDto(6.0, 60.0, 1200.0);
        $actuators = new ActuatorInputDto(ventilation: true);

        $ref = new \ReflectionClass($service);
        $m = $ref->getMethod('processInterdependencies');
        $next = $m->invoke($service, $state, $actuators);

        $this->assertLessThan(1200.0, $next->co2_ppm);
    }

    #[Test]
    public function inverse_temperature_humidity_relation_when_cooling(): void
    {
        $service = new SimulationService;
        $state = new SimulationStateDto(5.0, 50.0, 700.0);

        $ref = new \ReflectionClass($service);
        $m = $ref->getMethod('applyInverseTemperatureHumidityRelation');
        $next = $m->invoke($service, $state, 7.0);

        $this->assertGreaterThan(50.0, $next->humidity_rh);
    }

    #[Test]
    public function door_open_applies_ambient_exchange(): void
    {
        config(['simulation.ambient' => ['temperature_c' => 22.0, 'humidity_rh' => 55.0, 'co2_ppm' => 420.0]]);
        config(['simulation.door' => ['exchange_efficiency' => 0.25]]);

        $service = new SimulationService;
        $state = new SimulationStateDto(6.0, 60.0, 1000.0, 42.0, 1013.25, 0.0, 0.0, 0.0, 0.0);
        $actuators = new ActuatorInputDto(doorOpen: true);

        $ref = new \ReflectionClass($service);
        $m = $ref->getMethod('processInterdependencies');
        $next = $m->invoke($service, $state, $actuators);

        $this->assertGreaterThan(6.0, $next->temperature_c);
        $this->assertLessThan(60.0, $next->humidity_rh);
        $this->assertLessThan(1000.0, $next->co2_ppm);
        $this->assertEquals(1.0, $next->door_open);
    }

    #[Test]
    public function pump_decreases_water_level(): void
    {
        config(['simulation.pump' => ['delta_humidity_rh' => -0.4, 'water_level_drop_per_tick' => 5.0]]);
        config(['simulation.water_level' => ['recovery_per_tick' => 0.8, 'condensation_humidity_factor' => 0.02]]);

        $service = new SimulationService;
        $state = new SimulationStateDto(6.0, 60.0, 800.0, 42.0, 1013.25, 0.0, 0.0, 50.0, 0.0);
        $actuators = new ActuatorInputDto(pump: true);

        $ref = new \ReflectionClass($service);
        $m = $ref->getMethod('processInterdependencies');
        $next = $m->invoke($service, $state, $actuators);

        $this->assertLessThan(50.0, $next->water_level_pct);
        $this->assertEquals(1.0, $next->pump_running);
    }

    #[Test]
    public function pump_off_increases_water_level(): void
    {
        config(['simulation.water_level' => ['recovery_per_tick' => 2.0, 'condensation_humidity_factor' => 0.02]]);

        $service = new SimulationService;
        $state = new SimulationStateDto(6.0, 60.0, 800.0, 42.0, 1013.25, 0.0, 0.0, 30.0, 0.0);
        $actuators = new ActuatorInputDto(pump: false);

        $ref = new \ReflectionClass($service);
        $m = $ref->getMethod('processInterdependencies');
        $next = $m->invoke($service, $state, $actuators);

        $this->assertGreaterThan(30.0, $next->water_level_pct);
    }
}
