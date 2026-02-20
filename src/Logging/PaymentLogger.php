<?php

namespace Frolax\Payment\Logging;

use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\Enums\LogLevel;
use Frolax\Payment\Models\PaymentLog;
use Illuminate\Support\Facades\Log;

class PaymentLogger implements PaymentLoggerContract
{
    protected LogLevel $configuredLevel;

    protected array $redactedKeys;

    protected string $channel;

    protected bool $dbLogging;

    public function __construct()
    {
        $this->configuredLevel = LogLevel::from(config('payments.logging.level', 'basic'));
        $this->redactedKeys = config('payments.logging.redacted_keys', []);
        $this->channel = config('payments.logging.channel', config('logging.default'));
        $this->dbLogging = config('payments.logging.db_logging', true);
    }

    public function log(string $level, string $category, string $message, array $context = []): void
    {
        $requiredLevel = match ($level) {
            'error' => LogLevel::ErrorsOnly,
            'warning', 'info' => LogLevel::Basic,
            'debug' => LogLevel::Debug,
            default => LogLevel::Verbose,
        };

        if (! $this->configuredLevel->allows($requiredLevel)) {
            return;
        }

        // Flatten context to dot-notation
        $flatContext = CanonicalPayload::flattenDot($context);

        // Redact sensitive keys
        $redactedFlat = $this->redact($flatContext);
        $redactedNested = $this->redactNested($context);

        // Log to Laravel channel
        Log::channel($this->channel)->$level("[Payment] [{$category}] {$message}", $redactedFlat);

        // Log to DB if enabled
        if ($this->dbLogging && config('payments.persistence.enabled') && config('payments.persistence.logs')) {
            try {
                PaymentLog::create([
                    'level' => $level,
                    'category' => $category,
                    'message' => $message,
                    'gateway_name' => $flatContext['gateway.name'] ?? null,
                    'profile' => $flatContext['profile'] ?? null,
                    'tenant_id' => $flatContext['tenant_id'] ?? null,
                    'payment_id' => $flatContext['payment.id'] ?? null,
                    'attempt_id' => $flatContext['attempt.id'] ?? null,
                    'context_flat' => $redactedFlat,
                    'context_nested' => $redactedNested,
                    'occurred_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Silently fail DB logging to avoid breaking payment flow
                Log::channel($this->channel)->warning("[Payment] [logger] Failed to write DB log: {$e->getMessage()}");
            }
        }
    }

    public function info(string $category, string $message, array $context = []): void
    {
        $this->log('info', $category, $message, $context);
    }

    public function warning(string $category, string $message, array $context = []): void
    {
        $this->log('warning', $category, $message, $context);
    }

    public function error(string $category, string $message, array $context = []): void
    {
        $this->log('error', $category, $message, $context);
    }

    public function debug(string $category, string $message, array $context = []): void
    {
        $this->log('debug', $category, $message, $context);
    }

    /**
     * Redact sensitive keys in a flat dot-notation array.
     */
    protected function redact(array $flatData): array
    {
        foreach ($flatData as $key => $value) {
            foreach ($this->redactedKeys as $redactedKey) {
                if (str_contains(strtolower($key), strtolower($redactedKey))) {
                    $flatData[$key] = '[REDACTED]';
                    break;
                }
            }
        }

        return $flatData;
    }

    /**
     * Redact sensitive keys in nested array.
     */
    protected function redactNested(array $data): array
    {
        foreach ($data as $key => &$value) {
            $shouldRedact = false;

            foreach ($this->redactedKeys as $redactedKey) {
                if (str_contains(strtolower((string) $key), strtolower($redactedKey))) {
                    $shouldRedact = true;
                    break;
                }
            }

            if ($shouldRedact) {
                $value = '[REDACTED]';
            } elseif (is_array($value)) {
                $value = $this->redactNested($value);
            }
        }

        return $data;
    }
}
