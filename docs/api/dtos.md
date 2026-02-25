# DTOs (API Reference)

All Data Transfer Objects in `Frolax\Payment\Data`. All Data are `final readonly` classes.

## Payload

The core payload sent to every gateway driver.

```php
final readonly class Payload
{
    public string $idempotencyKey;
    public Order $order;
    public Money $money;
    public ?Customer $customer;
    public ?UrlsDTO $urls;
    public ?ContextDTO $context;
    public array $metadata;
    public array $extra;

    public static function fromArray(array $data): static;
    public function toArray(): array;
    public function toDotArray(): array;
    public static function flattenDot(array $array, string $prepend = ''): array;
}
```

## Money

```php
final readonly class Money
{
    public float|int $amount;  // Must be positive
    public string $currency;   // 3-letter ISO code, auto-uppercased

    public static function fromArray(array $data): static;
    public function toArray(): array;
}
```

## Order

```php
final readonly class Order
{
    public string $id;
    public ?string $description;
    /** @var OrderItemDTO[] */
    public array $items;

    public static function fromArray(array $data): static;
    public function toArray(): array;
}
```

## OrderItemDTO

```php
final readonly class OrderItemDTO
{
    public string $name;
    public int $quantity;
    public float|int $unitPrice;
    public ?string $description;
    public ?string $sku;
    public array $metadata;

    public static function fromArray(array $data): static;
    public function toArray(): array;
}
```

## Customer

```php
final readonly class Customer
{
    public ?string $name;
    public ?string $email;
    public ?string $phone;
    public ?Address $address;

    public static function fromArray(?array $data): ?static;
    public function toArray(): array;
}
```

## Address

```php
final readonly class Address
{
    public ?string $line1;
    public ?string $line2;
    public ?string $city;
    public ?string $state;
    public ?string $postalCode;
    public ?string $country;

    public static function fromArray(?array $data): ?static;
    public function toArray(): array;
}
```

## UrlsDTO

```php
final readonly class UrlsDTO
{
    public ?string $return;
    public ?string $cancel;
    public ?string $webhook;

    public static function fromArray(?array $data): ?static;
    public function toArray(): array;
}
```

## ContextDTO

```php
final readonly class ContextDTO
{
    public ?string $ip;
    public ?string $userAgent;
    public ?string $locale;

    public static function fromArray(?array $data): ?static;
    public function toArray(): array;
}
```

## Credentials

```php
final readonly class Credentials
{
    public string $gateway;
    public string $profile;
    public array $credentials;  // Raw key-value pairs

    public function get(string $key, mixed $default = null): mixed;
    public function toSafeArray(): array;  // Credentials masked as [REDACTED]
}
```

## GatewayResult

Uniform result returned by all driver operations.

```php
final readonly class GatewayResult
{
    public PaymentStatus $status;
    public ?string $gatewayReference;
    public ?string $redirectUrl;
    public array $gatewayResponse;
    public array $metadata;

    public function isSuccessful(): bool;
    public function isPending(): bool;
    public function isFailed(): bool;
    public function requiresRedirect(): bool;
}
```

## RefundPayload

```php
final readonly class RefundPayload
{
    public string $paymentId;
    public ?string $gatewayReference;
    public Money $money;
    public ?string $reason;
    public array $metadata;
    public array $extra;

    public static function fromArray(array $data): static;
    public function toArray(): array;
}
```

## StatusPayload

```php
final readonly class StatusPayload
{
    public string $paymentId;
    public ?string $gatewayReference;
    public array $extra;

    public static function fromArray(array $data): static;
    public function toArray(): array;
}
```
