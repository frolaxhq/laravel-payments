<?php

namespace Frolax\Payment\Credentials;

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Data\Credentials;

class CompositeCredentialsRepository implements CredentialsRepositoryContract
{
    /**
     * @param  CredentialsRepositoryContract[]  $repositories
     */
    public function __construct(
        protected array $repositories = [],
    ) {}

    public static function default(): self
    {
        return new self([
            new DatabaseCredentialsRepository,
            new EnvCredentialsRepository,
        ]);
    }

    public function get(string $gateway, string $profile, array $context = []): ?Credentials
    {
        foreach ($this->repositories as $repo) {
            $credentials = $repo->get($gateway, $profile, $context);

            if ($credentials !== null) {
                return $credentials;
            }
        }

        return null;
    }

    public function has(string $gateway, string $profile, array $context = []): bool
    {
        foreach ($this->repositories as $repo) {
            if ($repo->has($gateway, $profile, $context)) {
                return true;
            }
        }

        return false;
    }
}
