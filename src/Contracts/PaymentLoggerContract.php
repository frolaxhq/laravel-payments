<?php

namespace Frolax\Payment\Contracts;

interface PaymentLoggerContract
{
    /**
     * Log a payment event.
     *
     * @param  string  $level  Log level (info, warning, error, debug)
     * @param  string  $category  Category (e.g. "payment.create", "webhook.received")
     * @param  string  $message  Human-readable message
     * @param  array  $context  Context data (will be flattened to dot-notation for DB)
     */
    public function log(string $level, string $category, string $message, array $context = []): void;

    public function info(string $category, string $message, array $context = []): void;

    public function warning(string $category, string $message, array $context = []): void;

    public function error(string $category, string $message, array $context = []): void;

    public function debug(string $category, string $message, array $context = []): void;
}
