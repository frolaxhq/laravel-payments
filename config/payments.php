<?php

// config for Frolax/Payment

return [

    /*
     |--------------------------------------------------------------------------
     | Default Gateway
     |--------------------------------------------------------------------------
     |
     | The default payment gateway to use when none is explicitly specified.
     |
     */
    'default' => env('PAYMENT_GATEWAY', 'dummy'),

    /*
     |--------------------------------------------------------------------------
     | Gateways
     |--------------------------------------------------------------------------
     |
     | Define gateway-specific credentials and settings. Each gateway key should
     | match the driver's `name()` return value. Gateways registered via addon
     | packages are auto-discovered; you only need to add credentials here.
     |
     */
    'gateways' => [
        // 'stripe' => [
        //     'test' => [
        //         'key'            => env('STRIPE_TEST_KEY'),
        //         'secret'         => env('STRIPE_TEST_SECRET'),
        //         'webhook_secret' => env('STRIPE_TEST_WEBHOOK_SECRET'),
        //     ],
        //     'live' => [
        //         'key'            => env('STRIPE_LIVE_KEY'),
        //         'secret'         => env('STRIPE_LIVE_SECRET'),
        //         'webhook_secret' => env('STRIPE_LIVE_WEBHOOK_SECRET'),
        //     ],
        // ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Default Profile
     |--------------------------------------------------------------------------
     |
     | The default credential profile. Typically "test" during development
     | and "live" in production.
     |
     */
    'profile' => env('PAYMENT_PROFILE', 'test'),

    /*
     |--------------------------------------------------------------------------
     | Credential Storage
     |--------------------------------------------------------------------------
     |
     | How credentials are resolved at runtime:
     |   - "env"       : From config/payments.php (ENV variables)
     |   - "database"  : From payment_gateway_credentials table
     |   - "composite" : Database first, fallback to ENV
     |
     */
    'credential_storage' => env('PAYMENT_CREDENTIAL_STORAGE', 'env'),

    /*
     |--------------------------------------------------------------------------
     | Routes
     |--------------------------------------------------------------------------
     */
    'routes' => [
        'enabled' => true,
        'prefix' => 'payments',
        'middleware' => ['web'],
        'webhook_middleware' => [],
    ],

    /*
     |--------------------------------------------------------------------------
     | Logging
     |--------------------------------------------------------------------------
     |
     | Verbosity levels: "off", "errors_only", "basic", "verbose", "debug"
     |
     | Redacted keys will be replaced with "[REDACTED]" in all log output.
     |
     */
    'logging' => [
        'level' => env('PAYMENT_LOG_LEVEL', 'basic'),
        'channel' => env('PAYMENT_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),

        'redacted_keys' => [
            'secret',
            'password',
            'token',
            'card_number',
            'cvv',
            'cvc',
            'webhook_secret',
            'credentials',
            'authorization',
        ],

        'db_logging' => true,
    ],

    /*
     |--------------------------------------------------------------------------
     | Persistence
     |--------------------------------------------------------------------------
     |
     | Toggle database persistence for payments and related records.
     | Disable if you want to use your own persistence layer.
     |
     */
    'persistence' => [
        'enabled' => true,
        'payments' => true,
        'attempts' => true,
        'webhooks' => true,
        'refunds' => true,
        'logs' => true,
    ],

    /*
     |--------------------------------------------------------------------------
     | Idempotency
     |--------------------------------------------------------------------------
     */
    'idempotency' => [
        'auto_generate' => true,
    ],

    /*
     |--------------------------------------------------------------------------
     | Table Names
     |--------------------------------------------------------------------------
     |
     | Customize the database table names if needed.
     |
     */
    'tables' => [
        'gateways' => 'payment_gateways',
        'credentials' => 'payment_gateway_credentials',
        'payments' => 'payments',
        'attempts' => 'payment_attempts',
        'webhooks' => 'payment_webhook_events',
        'refunds' => 'payment_refunds',
        'logs' => 'payment_logs',
    ],
];