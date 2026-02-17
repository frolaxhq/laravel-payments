# Enums (API Reference)

All enums in `Frolax\Payment\Enums`.

## PaymentStatus

```php
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Expired = 'expired';

    public function isTerminal(): bool;    // completed, failed, cancelled, refunded, expired
    public function isSuccessful(): bool;  // completed only
}
```

## RefundStatus

```php
enum RefundStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
```

## AttemptStatus

```php
enum AttemptStatus: string
{
    case Initiated = 'initiated';
    case Sent = 'sent';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case TimedOut = 'timed_out';
}
```

## LogLevel

```php
enum LogLevel: string
{
    case Off = 'off';
    case ErrorsOnly = 'errors_only';
    case Basic = 'basic';
    case Verbose = 'verbose';
    case Debug = 'debug';

    public function priority(): int;
    public function allows(LogLevel $other): bool;
}
```

### Log Level Priority

| Level | Priority | What's Logged |
|-------|----------|---------------|
| `off` | 0 | Nothing |
| `errors_only` | 1 | Errors only |
| `basic` | 2 | Errors + warnings + key info |
| `verbose` | 3 | Everything except debug |
| `debug` | 4 | Everything |
