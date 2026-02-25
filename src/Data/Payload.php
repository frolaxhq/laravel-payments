<?php

namespace Frolax\Payment\Data;

use Illuminate\Support\Str;

final readonly class Payload
{
    public function __construct(
        public string $idempotencyKey,
        public Order $order,
        public Money $money,
        public ?Customer $customer = null,
        public ?Urls $urls = null,
        public ?Context $context = null,
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
            order: Order::fromArray($data['order'] ?? throw new \InvalidArgumentException('Order is required.')),
            money: Money::fromArray($data['money'] ?? throw new \InvalidArgumentException('Money is required.')),
            customer: Customer::fromArray($data['customer'] ?? null),
            urls: Urls::fromArray($data['urls'] ?? null),
            context: Context::fromArray($data['context'] ?? null),
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

            if (is_array($value) && ! empty($value)) {
                $result = array_merge($result, self::flattenDot($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}
