# CLI Commands

## payments:make-gateway

Generate a payment gateway driver skeleton.

```bash
php artisan payments:make-gateway {name} [options]
```

### Arguments

| Argument | Required | Description |
|----------|----------|-------------|
| `name` | Yes | Gateway name (e.g., `Stripe`, `Bkash`) |

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--addon` | `false` | Generate as a separate Composer package |
| `--key=` | snake_case of name | Gateway key (e.g., `bkash`) |
| `--display=` | Same as name | Display name (e.g., `"bKash"`) |
| `--capabilities=` | `redirect,webhook` | Comma-separated capabilities |
| `--credentials=` | `key:required,secret:required` | Credential schema |
| `--profile-support=` | `test,live` | Supported profiles |
| `--routes=` | `universal` | Route mode |
| `--http-client=` | `guzzle` | HTTP client to use |
| `--namespace=` | `Frolax\Payments\Gateways` | Base namespace |

### Examples

```bash
# Basic inline driver
php artisan payments:make-gateway Stripe

# Full addon package
php artisan payments:make-gateway Bkash --addon \
    --key=bkash \
    --display="bKash" \
    --capabilities=redirect,webhook,refund \
    --credentials=app_key:required,app_secret:required,username:required,password:required

# Custom namespace
php artisan payments:make-gateway PayPal --namespace=App\\Gateways
```

### Generated Files (Inline)

```
app/Payment/Gateways/{Name}/
├── {Name}Driver.php
├── config_snippet.php
└── README.md

tests/Payment/Gateways/{Name}/
└── {Name}DriverTest.php
```

### Generated Files (Addon)

```
packages/frolax/payment-{name}/
├── composer.json
├── config/payment-{name}.php
├── src/
│   ├── {Name}Driver.php
│   ├── {Name}GatewayAddon.php
│   └── {Name}ServiceProvider.php
├── tests/{Name}DriverTest.php
└── docs/README.md
```

---

## payments:gateways

List all discovered payment gateways.

```bash
php artisan payments:gateways
```

### Output

```
+--------+--------------+--------------+------------------+---------------+-------+
| Key    | Display Name | Driver       | Capabilities     | Config Source  | Type  |
+--------+--------------+--------------+------------------+---------------+-------+
| stripe | Stripe       | StripeDriver | redirect,webhook | env           | core  |
| bkash  | bKash        | BkashDriver  | redirect,webhook | env+db        | addon |
+--------+--------------+--------------+------------------+---------------+-------+
```

| Column | Description |
|--------|-------------|
| **Key** | Gateway identifier used in code |
| **Display Name** | Human-readable name |
| **Driver** | Driver class (or `(callable)`) |
| **Capabilities** | Implemented capabilities |
| **Config Source** | Where credentials are found (`env`, `db`, `env+db`, `—`) |
| **Type** | `core` (manual) or `addon` (auto-discovered) |

---

## payments:credentials:sync

Validate that required credentials exist for gateways.

```bash
php artisan payments:credentials:sync [options]
```

### Options

| Option | Description |
|--------|-------------|
| `--gateway=` | Check specific gateway only |
| `--profile=` | Check specific profile (`test`/`live`) |
| `--tenant=` | Check for specific tenant ID |

### Examples

```bash
# Check all gateways for default profile
php artisan payments:credentials:sync

# Check specific gateway
php artisan payments:credentials:sync --gateway=stripe

# Check live credentials for a tenant
php artisan payments:credentials:sync --gateway=stripe --profile=live --tenant=tenant-abc
```

### Output

```
  stripe: ✓ All required credentials present
  bkash: Missing required keys: app_secret, password

Some gateways have missing credentials.
```

---

## payments:webhooks:replay

Replay a stored webhook event.

```bash
php artisan payments:webhooks:replay {id}
```

### Arguments

| Argument | Description |
|----------|-------------|
| `id` | The webhook event's ULID |

### What It Does

1. Loads the stored webhook from `payment_webhook_events`
2. Shows event details and asks for confirmation
3. Reconstructs the HTTP request from stored headers + payload
4. Processes through the driver's `verify()` method
5. Updates payment status
6. Re-dispatches `WebhookReceived` event
7. Marks event as processed

### Example

```bash
php artisan payments:webhooks:replay 01HXYZ123456789

# Output:
# Replaying webhook event [01HXYZ123456789]
#   Gateway: stripe
#   Event Type: payment.completed
#   Gateway Reference: ch_1234
#   Originally Processed: Yes
#
# Do you want to proceed with the replay? (yes/no) [yes]
#
# Webhook event [01HXYZ123456789] replayed successfully. Status: completed
```
