<?php

namespace Frolax\Payment\Data;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int|float $amount,
        public string $currency,
    ) {
        if ($this->amount < 0) {
            throw new InvalidArgumentException('Amount must be non-negative.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            amount: $data['amount'] ?? throw new InvalidArgumentException('Money amount is required.'),
            currency: strtoupper($data['currency'] ?? throw new InvalidArgumentException('Money currency is required.')),
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
