<?php

namespace Frolax\Payment\Credentials;

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\DTOs\CredentialsDTO;

class EnvCredentialsRepository implements CredentialsRepositoryContract
{
    public function get(string $gateway, string $profile, array $context = []): ?CredentialsDTO
    {
        $credentials = config("payments.gateways.{$gateway}.{$profile}");

        if ($credentials === null || $credentials === []) {
            return null;
        }

        return new CredentialsDTO(
            gateway: $gateway,
            profile: $profile,
            credentials: $credentials,
            tenantId: $context['tenant_id'] ?? null,
        );
    }

    public function has(string $gateway, string $profile, array $context = []): bool
    {
        return $this->get($gateway, $profile, $context) !== null;
    }
}
