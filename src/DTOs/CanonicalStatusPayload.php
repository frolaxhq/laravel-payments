<?php

namespace Frolax\Payment\DTOs;

final readonly class CanonicalStatusPayload
{
    public function __construct(
        public string $paymentId,
        public ?string $gatewayReference = null,
        public array $extra = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            paymentId: $data['payment_id'] ?? throw new \InvalidArgumentException('payment_id is required.'),
            gatewayReference: $data['gateway_reference'] ?? null,
            extra: $data['extra'] ?? [],
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'payment_id' => $this->paymentId,
            'gateway_reference' => $this->gatewayReference,
            'extra' => $this->extra ?: null,
        ], fn ($v) => $v !== null);
    }
}
