<?php

namespace Frolax\Payment\Data;

final readonly class Urls
{
    public function __construct(
        public ?string $return = null,
        public ?string $cancel = null,
        public ?string $webhook = null,
    ) {}

    public static function fromArray(?array $data): ?self
    {
        if ($data === null || $data === []) {
            return null;
        }

        return new self(
            return: $data['return'] ?? null,
            cancel: $data['cancel'] ?? null,
            webhook: $data['webhook'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'return' => $this->return,
            'cancel' => $this->cancel,
            'webhook' => $this->webhook,
        ], fn ($v) => $v !== null);
    }
}
