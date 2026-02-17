# Environment Variables

All environment variables supported by Laravel Payments.

## Core Settings

| Variable | Default | Description |
|----------|---------|-------------|
| `PAYMENT_GATEWAY` | `stripe` | Default gateway |
| `PAYMENT_PROFILE` | `test` | Default credential profile (`test`/`live`) |
| `PAYMENT_CREDENTIAL_STORAGE` | `env` | Credential storage mode (`env`/`database`/`composite`) |

## Logging

| Variable | Default | Description |
|----------|---------|-------------|
| `PAYMENT_LOG_LEVEL` | `basic` | Log verbosity: `off`, `errors_only`, `basic`, `verbose`, `debug` |
| `PAYMENT_LOG_CHANNEL` | `null` | Laravel log channel (null = default) |

## Gateway Credentials

Gateway credentials follow the pattern:

```
{GATEWAY}_{PROFILE}_{KEY}
```

### Stripe

| Variable | Profile | Description |
|----------|---------|-------------|
| `STRIPE_TEST_KEY` | test | Stripe test secret key |
| `STRIPE_TEST_SECRET` | test | Stripe test webhook secret |
| `STRIPE_TEST_WEBHOOK_SECRET` | test | Stripe test webhook signing secret |
| `STRIPE_LIVE_KEY` | live | Stripe live secret key |
| `STRIPE_LIVE_SECRET` | live | Stripe live webhook secret |
| `STRIPE_LIVE_WEBHOOK_SECRET` | live | Stripe live webhook signing secret |

### bKash (Example)

| Variable | Profile | Description |
|----------|---------|-------------|
| `BKASH_TEST_APP_KEY` | test | bKash sandbox app key |
| `BKASH_TEST_APP_SECRET` | test | bKash sandbox app secret |
| `BKASH_TEST_USERNAME` | test | bKash sandbox username |
| `BKASH_TEST_PASSWORD` | test | bKash sandbox password |

## Adding Your Own

When creating a gateway driver, establish your own ENV naming convention:

```dotenv
# Pattern: GATEWAY_PROFILE_KEY
MYGW_TEST_API_KEY=...
MYGW_TEST_API_SECRET=...
MYGW_LIVE_API_KEY=...
MYGW_LIVE_API_SECRET=...
```

Map them in `config/payments.php`:

```php
'gateways' => [
    'mygw' => [
        'test' => [
            'api_key' => env('MYGW_TEST_API_KEY'),
            'api_secret' => env('MYGW_TEST_API_SECRET'),
        ],
        'live' => [
            'api_key' => env('MYGW_LIVE_API_KEY'),
            'api_secret' => env('MYGW_LIVE_API_SECRET'),
        ],
    ],
],
```
