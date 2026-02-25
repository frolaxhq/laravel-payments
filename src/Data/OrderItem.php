<?php

namespace Frolax\Payment\Data;

final readonly class OrderItem
{
    public function __construct(
        public string $name,
        public int $quantity,
        public int|float $unitPrice,
        public ?string $sku = null,
        public ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            quantity: $data['quantity'] ?? 1,
            unitPrice: $data['unit_price'] ?? 0,
            sku: $data['sku'] ?? null,
            description: $data['description'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'sku' => $this->sku,
            'description' => $this->description,
        ], fn ($v) => $v !== null);
    }
}
