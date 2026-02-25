<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\Data\Credentials;

interface CredentialsRepositoryContract
{
    /**
     * Resolve credentials for the given gateway, profile, and context.
     *
     * @param  string  $gateway  Gateway name
     * @param  string  $profile  Profile (test/live)
     * @param  array  $context  Optional context (e.g. ['tenant_id' => ...])
     */
    public function get(string $gateway, string $profile, array $context = []): ?Credentials;

    /**
     * Check if credentials exist for the given parameters.
     */
    public function has(string $gateway, string $profile, array $context = []): bool;
}
