<?php

namespace Frolax\Payment\DTOs;

final readonly class PlanDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public MoneyDTO $money,
        public string $interval,
        public int $intervalCount = 1,
        public ?string $description = null,
        public ?int $trialDays = null,
        public array $features = [],
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            money: MoneyDTO::fromArray($data['money']),
            interval: $data['interval'],
            intervalCount: $data['interval_count'] ?? 1,
            description: $data['description'] ?? null,
            trialDays: $data['trial_days'] ?? null,
            features: $data['features'] ?? [],
            metadata: $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'money' => $this->money->toArray(),
            'interval' => $this->interval,
            'interval_count' => $this->intervalCount,
            'description' => $this->description,
            'trial_days' => $this->trialDays,
            'features' => $this->features,
            'metadata' => $this->metadata,
        ];
    }
}
