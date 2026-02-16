<?php

namespace Frolax\Payment\DTOs;

final readonly class ContextDTO
{
    public function __construct(
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $locale = null,
    ) {}

    public static function fromArray(?array $data): ?self
    {
        if ($data === null || $data === []) {
            return null;
        }

        return new self(
            ip: $data['ip'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            locale: $data['locale'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'locale' => $this->locale,
        ], fn ($v) => $v !== null);
    }
}
