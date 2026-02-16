<?php

namespace Frolax\Payment\DTOs;

use Frolax\Payment\Enums\PaymentStatus;

final readonly class GatewayResult
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $gatewayReference = null,
        public ?string $redirectUrl = null,
        public array $gatewayResponse = [],
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
        public array $metadata = [],
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending || $this->status === PaymentStatus::Processing;
    }

    public function requiresRedirect(): bool
    {
        return $this->redirectUrl !== null;
    }

    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status->value,
            'gateway_reference' => $this->gatewayReference,
            'redirect_url' => $this->redirectUrl,
            'gateway_response' => $this->gatewayResponse ?: null,
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
            'metadata' => $this->metadata ?: null,
        ], fn ($v) => $v !== null);
    }
}
