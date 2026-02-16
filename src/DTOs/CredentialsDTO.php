<?php

namespace Frolax\Payment\DTOs;

final readonly class CredentialsDTO
{
    public function __construct(
        public string $gateway,
        public string $profile,
        public array $credentials,
        public ?string $tenantId = null,
        public ?string $label = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            gateway: $data['gateway'] ?? throw new \InvalidArgumentException('Gateway is required.'),
            profile: $data['profile'] ?? 'test',
            credentials: $data['credentials'] ?? [],
            tenantId: $data['tenant_id'] ?? null,
            label: $data['label'] ?? null,
        );
    }

    /**
     * Get a specific credential value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'gateway' => $this->gateway,
            'profile' => $this->profile,
            'credentials' => $this->credentials,
            'tenant_id' => $this->tenantId,
            'label' => $this->label,
        ];
    }

    /**
     * Return a safe version with credentials masked.
     */
    public function toSafeArray(): array
    {
        return [
            'gateway' => $this->gateway,
            'profile' => $this->profile,
            'credentials' => '[REDACTED]',
            'tenant_id' => $this->tenantId,
            'label' => $this->label,
        ];
    }
}
