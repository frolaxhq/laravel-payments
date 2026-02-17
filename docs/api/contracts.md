# Contracts (API Reference)

All interfaces in the `Frolax\Payment\Contracts` namespace.

## GatewayDriverContract

Core interface every gateway driver must implement.

```php
namespace Frolax\Payment\Contracts;

interface GatewayDriverContract
{
    public function name(): string;
    public function create(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;
    public function verify(Request $request, CredentialsDTO $credentials): GatewayResult;
    public function setCredentials(CredentialsDTO $credentials): static;
    public function capabilities(): array;
}
```

---

## SupportsHostedRedirect

```php
interface SupportsHostedRedirect
{
    public function getRedirectUrl(GatewayResult $result): ?string;
}
```

---

## SupportsWebhookVerification

```php
interface SupportsWebhookVerification
{
    public function verifyWebhookSignature(Request $request, CredentialsDTO $credentials): bool;
    public function parseWebhookEventType(Request $request): ?string;
    public function parseWebhookGatewayReference(Request $request): ?string;
}
```

---

## SupportsRefund

```php
interface SupportsRefund
{
    public function refund(CanonicalRefundPayload $payload, CredentialsDTO $credentials): GatewayResult;
}
```

---

## SupportsStatusQuery

```php
interface SupportsStatusQuery
{
    public function status(CanonicalStatusPayload $payload, CredentialsDTO $credentials): GatewayResult;
}
```

---

## SupportsTokenization <Badge type="info" text="Future" />

```php
interface SupportsTokenization
{
    public function tokenize(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;
    public function chargeToken(string $token, CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;
}
```

---

## SupportsInstallments <Badge type="info" text="Future" />

```php
interface SupportsInstallments
{
    public function installmentPlans(CanonicalPayload $payload, CredentialsDTO $credentials): array;
}
```

---

## CredentialsRepositoryContract

```php
interface CredentialsRepositoryContract
{
    public function get(string $gateway, string $profile = 'test', array $context = []): ?CredentialsDTO;
    public function has(string $gateway, string $profile = 'test', array $context = []): bool;
}
```

**Implementations:**
- `EnvCredentialsRepository` — Reads from config
- `DatabaseCredentialsRepository` — Reads from DB with tenant/time/priority
- `CompositeCredentialsRepository` — DB first, fallback to ENV

---

## PaymentLoggerContract

```php
interface PaymentLoggerContract
{
    public function log(string $level, string $category, string $message, array $context = []): void;
    public function info(string $category, string $message, array $context = []): void;
    public function warning(string $category, string $message, array $context = []): void;
    public function error(string $category, string $message, array $context = []): void;
    public function debug(string $category, string $message, array $context = []): void;
}
```

---

## GatewayAddonContract

Contract for auto-discovered gateway addon packages.

```php
interface GatewayAddonContract
{
    public function gatewayKey(): string;
    public function displayName(): string;
    public function driverClass(): string|callable;
    public function capabilities(): array;
    public function credentialSchema(): array;
    public function defaultConfig(): array;
}
```
