<?php

namespace App\DataTransferObjects;

readonly class ActuatorInputDto
{
    public function __construct(
        public bool $acStatus = false,
        public float $acTemp = 22.0,
        public bool $humidifier = false,
        public bool $heater = false,
        public bool $ventilation = false,
        public bool $mainLight = false,
        public bool $irLamp = false,
        public bool $pump = false,
        public bool $doorOpen = false,
        public bool $freshenerOn = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            acStatus: (bool) ($data['acStatus'] ?? false),
            acTemp: (float) ($data['acTemp'] ?? 22.0),
            humidifier: (bool) ($data['humidifier'] ?? false),
            heater: (bool) ($data['heater'] ?? false),
            ventilation: (bool) ($data['ventilation'] ?? false),
            mainLight: (bool) ($data['mainLight'] ?? false),
            irLamp: (bool) ($data['irLamp'] ?? false),
            pump: (bool) ($data['pump'] ?? false),
            doorOpen: (bool) ($data['doorOpen'] ?? false),
            freshenerOn: (bool) ($data['freshenerOn'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'acStatus' => $this->acStatus,
            'acTemp' => $this->acTemp,
            'humidifier' => $this->humidifier,
            'heater' => $this->heater,
            'ventilation' => $this->ventilation,
            'mainLight' => $this->mainLight,
            'irLamp' => $this->irLamp,
            'pump' => $this->pump,
            'doorOpen' => $this->doorOpen,
            'freshenerOn' => $this->freshenerOn,
        ];
    }
}
