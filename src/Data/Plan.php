<?php

namespace Frolax\Payment\Data;

final readonly class Plan
{
    public function __construct(
        public string $priceId,
        public string $planId,
        public string $name,
        public Money $money,
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
            priceId: $data['priceId'],
            planId: $data['planId'],
            name: $data['name'],
            money: Money::fromArray($data['money']),
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
            'priceId' => $this->priceId,
            'planId' => $this->planId,
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
