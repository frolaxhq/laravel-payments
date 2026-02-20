<?php

namespace Frolax\Payment;

/**
 * Immutable value object encapsulating all payment config reads.
 *
 * Avoids repeated config() calls throughout the payment flow.
 */
class PaymentConfig
{
    public readonly bool $persistenceEnabled;

    public readonly bool $persistPayments;

    public readonly bool $persistAttempts;

    public readonly bool $persistRefunds;

    public readonly bool $persistWebhooks;

    public readonly bool $persistLogs;

    public readonly string $defaultGateway;

    public readonly string $defaultProfile;

    public readonly bool $dbLogging;

    public readonly bool $autoGenerateIdempotencyKey;

    public function __construct()
    {
        $this->persistenceEnabled = (bool) config('payments.persistence.enabled', true);
        $this->persistPayments = (bool) config('payments.persistence.payments', true);
        $this->persistAttempts = (bool) config('payments.persistence.attempts', true);
        $this->persistRefunds = (bool) config('payments.persistence.refunds', true);
        $this->persistWebhooks = (bool) config('payments.persistence.webhooks', true);
        $this->persistLogs = (bool) config('payments.persistence.logs', true);
        $this->defaultGateway = (string) config('payments.default', 'dummy');
        $this->defaultProfile = (string) config('payments.profile', 'test');
        $this->dbLogging = (bool) config('payments.logging.db_logging', false);
        $this->autoGenerateIdempotencyKey = (bool) config('payments.idempotency.auto_generate', true);
    }

    public function shouldPersistPayments(): bool
    {
        return $this->persistenceEnabled && $this->persistPayments;
    }

    public function shouldPersistAttempts(): bool
    {
        return $this->persistenceEnabled && $this->persistAttempts;
    }

    public function shouldPersistRefunds(): bool
    {
        return $this->persistenceEnabled && $this->persistRefunds;
    }

    public function shouldPersistWebhooks(): bool
    {
        return $this->persistenceEnabled && $this->persistWebhooks;
    }

    public function shouldPersistLogs(): bool
    {
        return $this->persistenceEnabled && $this->persistLogs;
    }
}
