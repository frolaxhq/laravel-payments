<?php

namespace Frolax\Payment\DTOs;

use Illuminate\Support\Str;

final readonly class CanonicalPayload
{
    public function __construct(
        public string $idempotencyKey,
        public OrderDTO $order,
        public MoneyDTO $money,
        public ?CustomerDTO $customer = null,
        public ?UrlsDTO $urls = null,
        public ?ContextDTO $context = null,
        public array $metadata = [],
        public array $extra = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $idempotencyKey = $data['idempotency_key']
            ?? (config('payments.idempotency.auto_generate', true) ? (string) Str::ulid() : null);

        if ($idempotencyKey === null) {
            throw new \InvalidArgumentException('idempotency_key is required.');
        }

        return new self(
            idempotencyKey: $idempotencyKey,
            order: OrderDTO::fromArray($data['order'] ?? throw new \InvalidArgumentException('Order is required.')),
            money: MoneyDTO::fromArray($data['money'] ?? throw new \InvalidArgumentException('Money is required.')),
            customer: CustomerDTO::fromArray($data['customer'] ?? null),
            urls: UrlsDTO::fromArray($data['urls'] ?? null),
            context: ContextDTO::fromArray($data['context'] ?? null),
            metadata: $data['metadata'] ?? [],
            extra: $data['extra'] ?? [],
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'idempotency_key' => $this->idempotencyKey,
            'order' => $this->order->toArray(),
            'money' => $this->money->toArray(),
            'customer' => $this->customer?->toArray(),
            'urls' => $this->urls?->toArray(),
            'context' => $this->context?->toArray(),
            'metadata' => $this->metadata ?: null,
            'extra' => $this->extra ?: null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Flatten to dot-notation for logging.
     */
    public function toDotArray(): array
    {
        return self::flattenDot($this->toArray());
    }

    public static function flattenDot(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && !empty($value)) {
                $result = array_merge($result, self::flattenDot($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}
