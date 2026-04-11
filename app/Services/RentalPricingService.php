<?php

namespace App\Services;

use App\Models\Container;
use App\Models\User;
use Carbon\CarbonImmutable;

class RentalPricingService
{
    /**
     * @param  array<int, string>  $cargoTypes
     * @return array<string, float|int|string|array<int, string>>
     */
    public function calculate(
        User $user,
        Container $container,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        float $distanceKm,
        int $estimatedTransitDays,
        array $cargoTypes = [],
        ?float $requestedWeight = null,
        ?float $cargoVolumeCbm = null,
        ?int $packageCount = null,
        ?float $cargoValue = null,
        string $priority = 'normal',
        string $deliveryMode = 'port_to_port',
        string $loadingType = 'fcl',
        string $sustainabilityPref = 'standard',
        bool $insuranceRequired = false,
        bool $hazardousMaterial = false,
        bool $requiresCustomsClearance = false,
        bool $requiresEscort = false,
        bool $sealRequired = false
    ): array {
        $days = max(1, $startDate->diffInDays($endDate));
        $distance = max(0, $distanceKm);
        $basePrice = 5000.00;
        $containerMultiplier = $this->containerTypeMultiplier($container->type);
        $distanceComponent = $distance * 0.10;
        $dailyComponent = $days * 20.00;
        $cargoComponent = count($cargoTypes) * 75.00;
        $weightComponent = ($requestedWeight && $requestedWeight > 0) ? ($requestedWeight * 0.02) : 0.00;
        $volumeComponent = ($cargoVolumeCbm && $cargoVolumeCbm > 0) ? ($cargoVolumeCbm * 18.00) : 0.00;
        $packageComponent = ($packageCount && $packageCount > 0) ? ($packageCount * 4.00) : 0.00;
        $iotSurcharge = $container->iot_active ? 250.00 : 0.00;
        $transitUrgencySurcharge = $days < $estimatedTransitDays ? 800.00 : 0.00;
        $prioritySurcharge = match (strtolower($priority)) {
            'express' => 1200.00,
            'urgent' => 550.00,
            default => 0.00,
        };
        $hazardousSurcharge = $hazardousMaterial ? 900.00 : 0.00;
        $customsHandlingFee = $requiresCustomsClearance ? 220.00 : 0.00;
        $deliveryModeFee = match (strtolower($deliveryMode)) {
            'door_to_door' => 600.00,
            'door_to_port', 'port_to_door' => 320.00,
            default => 0.00,
        };
        $lclHandlingFee = strtolower($loadingType) === 'lcl' ? 180.00 : 0.00;
        $escortFee = $requiresEscort ? 450.00 : 0.00;
        $sealFee = $sealRequired ? 75.00 : 0.00;
        $sustainabilityFee = match (strtolower($sustainabilityPref)) {
            'eco_optimized' => 140.00,
            'low_emission' => 260.00,
            default => 0.00,
        };
        $insuranceCost = $insuranceRequired
            ? max(120.00, (float) ($cargoValue ?? 0) * 0.012)
            : 0.00;

        $subtotal = ($basePrice * $containerMultiplier)
            + $distanceComponent
            + $dailyComponent
            + $cargoComponent
            + $weightComponent
            + $volumeComponent
            + $packageComponent
            + $iotSurcharge
            + $transitUrgencySurcharge
            + $prioritySurcharge
            + $hazardousSurcharge
            + $customsHandlingFee
            + $deliveryModeFee
            + $lclHandlingFee
            + $escortFee
            + $sealFee
            + $sustainabilityFee
            + $insuranceCost;

        $longTermDiscount = $days > 30 ? ($subtotal * 0.10) : 0.00;
        $discountedTotal = max(0, $subtotal - $longTermDiscount);

        $taxRate = (float) ($user->country?->interest_tax ?? 0.00);
        $taxAmount = $discountedTotal * ($taxRate / 100);
        $finalTotal = $discountedTotal + $taxAmount;

        return [
            'days' => $days,
            'currency' => 'USD',
            'base_price' => round($basePrice, 2),
            'container_multiplier' => $containerMultiplier,
            'distance_component' => round($distanceComponent, 2),
            'daily_component' => round($dailyComponent, 2),
            'cargo_component' => round($cargoComponent, 2),
            'weight_component' => round($weightComponent, 2),
            'volume_component' => round($volumeComponent, 2),
            'package_component' => round($packageComponent, 2),
            'iot_surcharge' => round($iotSurcharge, 2),
            'transit_urgency_surcharge' => round($transitUrgencySurcharge, 2),
            'priority_surcharge' => round($prioritySurcharge, 2),
            'hazardous_surcharge' => round($hazardousSurcharge, 2),
            'customs_handling_fee' => round($customsHandlingFee, 2),
            'delivery_mode_fee' => round($deliveryModeFee, 2),
            'lcl_handling_fee' => round($lclHandlingFee, 2),
            'escort_fee' => round($escortFee, 2),
            'seal_fee' => round($sealFee, 2),
            'sustainability_fee' => round($sustainabilityFee, 2),
            'insurance_cost' => round($insuranceCost, 2),
            'long_term_discount' => round($longTermDiscount, 2),
            'tax_rate' => round($taxRate, 2),
            'tax_amount' => round($taxAmount, 2),
            'estimated_total' => round($finalTotal, 2),
            'cargo_types' => array_values($cargoTypes),
        ];
    }

    private function containerTypeMultiplier(?string $type): float
    {
        return match (strtolower((string) $type)) {
            'high_cube' => 1.20,
            'open_top' => 1.25,
            'flat_rack' => 1.30,
            'refrigerated', 'reefer' => 1.40,
            default => 1.00,
        };
    }
}
