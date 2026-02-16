<?php

namespace Frolax\Payment\DTOs;

final readonly class OrderDTO
{
    /**
     * @param  OrderItemDTO[]  $items
     */
    public function __construct(
        public string $id,
        public ?string $description = null,
        public array $items = [],
    ) {}

    public static function fromArray(?array $data): ?self
    {
        if ($data === null || !isset($data['id'])) {
            return null;
        }

        $items = array_map(
            fn (array $item) => OrderItemDTO::fromArray($item),
            $data['items'] ?? []
        );

        return new self(
            id: $data['id'],
            description: $data['description'] ?? null,
            items: $items,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'description' => $this->description,
            'items' => array_map(fn (OrderItemDTO $item) => $item->toArray(), $this->items),
        ], fn ($v) => $v !== null && $v !== []);
    }
}
