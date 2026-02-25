<?php

namespace Frolax\Payment\Concerns;

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Exceptions\MissingCredentialsException;
use Frolax\Payment\GatewayRegistry;
use Frolax\Payment\PaymentConfig;

/**
 * Shared gateway context for all payment managers.
 *
 * Provides the fluent API: gateway(), usingContext(), withProfile(), usingCredentials()
 * and introspection: driver(), supports(), capabilities()
 */
trait HasGatewayContext
{
    protected ?string $gatewayName = null;

    protected ?string $profile = null;

    protected array $context = [];

    protected ?Credentials $oneOffCredentials = null;

    abstract protected function registry(): GatewayRegistry;

    abstract protected function credentialsRepo(): CredentialsRepositoryContract;

    abstract protected function config(): PaymentConfig;

    /**
     * Select a gateway by name.
     */
    public function gateway(?string $name = null): static
    {
        $clone = clone $this;
        $clone->gatewayName = $name ?? $this->config()->defaultGateway;

        return $clone;
    }

    /**
     * Set runtime context (e.g. tenant_id).
     */
    public function usingContext(array $context): static
    {
        $clone = clone $this;
        $clone->context = array_merge($clone->context, $context);

        return $clone;
    }

    /**
     * Select a credential profile (test/live).
     */
    public function withProfile(string $profile): static
    {
        $clone = clone $this;
        $clone->profile = $profile;

        return $clone;
    }

    /**
     * Use one-off credentials (not resolved from repo).
     */
    public function usingCredentials(array $credentials): static
    {
        $clone = clone $this;
        $clone->oneOffCredentials = new Credentials(
            gateway: $clone->resolveGatewayName(),
            profile: $clone->resolveProfile(),
            credentials: $credentials,
            tenantId: $clone->context['tenant_id'] ?? null,
        );

        return $clone;
    }

    /**
     * Get the resolved driver instance for the current gateway.
     */
    public function driver(): GatewayDriverContract
    {
        return $this->resolveDriver($this->resolveGatewayName());
    }

    /**
     * Check if the current gateway's driver implements a capability contract.
     *
     * @param  class-string  $capability  e.g. SupportsRecurring::class
     */
    public function supports(string $capability): bool
    {
        return $this->driver() instanceof $capability;
    }

    /**
     * Get the capability class-strings registered for this gateway.
     *
     * @return array<class-string>
     */
    public function capabilities(): array
    {
        return $this->registry()->capabilities($this->resolveGatewayName());
    }

    // -------------------------------------------------------
    // Internal resolution
    // -------------------------------------------------------

    protected function resolveGatewayName(): string
    {
        return $this->gatewayName ?? $this->config()->defaultGateway;
    }

    protected function resolveProfile(): string
    {
        return $this->profile ?? $this->config()->defaultProfile;
    }

    protected function resolveDriver(string $gateway): GatewayDriverContract
    {
        return $this->registry()->resolve($gateway);
    }

    protected function resolveCredentials(string $gateway): Credentials
    {
        if ($this->oneOffCredentials) {
            return $this->oneOffCredentials;
        }

        $profile = $this->resolveProfile();
        $credentials = $this->credentialsRepo()->get($gateway, $profile, $this->context);

        if ($credentials === null) {
            throw new MissingCredentialsException($gateway, $profile, $this->context['tenant_id'] ?? null);
        }

        return $credentials;
    }
}
