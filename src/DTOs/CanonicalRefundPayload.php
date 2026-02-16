<?php

namespace Frolax\Payment\DTOs;

final readonly class CanonicalRefundPayload
{
    public function __construct(
        public string $paymentId,
        public MoneyDTO $money,
        public ?string $reason = null,
        public ?string $idempotencyKey = null,
        public array $metadata = [],
        public array $extra = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            paymentId: $data['payment_id'] ?? throw new \InvalidArgumentException('payment_id is required.'),
            money: MoneyDTO::fromArray($data['money'] ?? throw new \InvalidArgumentException('Money is required.')),
            reason: $data['reason'] ?? null,
            idempotencyKey: $data['idempotency_key'] ?? (string) \Illuminate\Support\Str::ulid(),
            metadata: $data['metadata'] ?? [],
            extra: $data['extra'] ?? [],
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'payment_id' => $this->paymentId,
            'money' => $this->money->toArray(),
            'reason' => $this->reason,
            'idempotency_key' => $this->idempotencyKey,
            'metadata' => $this->metadata ?: null,
            'extra' => $this->extra ?: null,
        ], fn ($v) => $v !== null);
    }
}
