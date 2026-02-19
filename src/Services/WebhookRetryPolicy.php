<?php

namespace Frolax\Payment\Services;

class WebhookRetryPolicy
{
    protected int $maxAttempts;

    protected string $strategy;

    protected int $baseDelay;

    public function __construct()
    {
        $this->maxAttempts = config('payments.webhooks.retry_attempts', 3);
        $this->strategy = config('payments.webhooks.retry_backoff', 'exponential');
        $this->baseDelay = config('payments.webhooks.retry_delay_seconds', 60);
    }

    /**
     * Should the webhook be retried?
     */
    public function shouldRetry(int $currentAttempt): bool
    {
        return $currentAttempt < $this->maxAttempts;
    }

    /**
     * Get the delay in seconds before the next retry.
     */
    public function getDelay(int $currentAttempt): int
    {
        return match ($this->strategy) {
            'exponential' => $this->baseDelay * (2 ** ($currentAttempt - 1)),
            'linear' => $this->baseDelay * $currentAttempt,
            'fixed' => $this->baseDelay,
            default => $this->baseDelay,
        };
    }

    /**
     * Get the max retry attempts.
     */
    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }
}
