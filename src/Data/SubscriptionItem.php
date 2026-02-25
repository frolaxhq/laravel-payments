<?php

namespace Frolax\Payment\Data;

final readonly class SubscriptionItem
{
    public function __construct(
        public string $productId,
        public string $name,
        public int $quantity = 1,
        public ?Money $unitPrice = null,
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            productId: $data['product_id'],
            name: $data['name'],
            quantity: $data['quantity'] ?? 1,
            unitPrice: isset($data['unit_price']) ? Money::fromArray($data['unit_price']) : null,
            metadata: $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'product_id' => $this->productId,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice?->toArray(),
            'metadata' => $this->metadata,
        ], fn ($v) => $v !== null && $v !== []);
    }
}
