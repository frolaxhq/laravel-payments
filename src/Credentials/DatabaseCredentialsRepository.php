<?php

namespace Frolax\Payment\Credentials;

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Models\PaymentGatewayCredential;

class DatabaseCredentialsRepository implements CredentialsRepositoryContract
{
    public function get(string $gateway, string $profile, array $context = []): ?Credentials
    {
        $tenantId = $context['tenant_id'] ?? null;
        $now = now();

        $query = PaymentGatewayCredential::query()
            ->where('gateway_name', $gateway)
            ->where('profile', $profile)
            ->where('is_active', true);

        if ($tenantId !== null) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id');
            });
        } else {
            $query->whereNull('tenant_id');
        }

        // Time window filtering
        $query->where(function ($q) use ($now) {
            $q->where(function ($sub) {
                $sub->whereNull('effective_from')
                    ->whereNull('effective_to');
            })->orWhere(function ($sub) use ($now) {
                $sub->where('effective_from', '<=', $now)
                    ->where(function ($inner) use ($now) {
                        $inner->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $now);
                    });
            });
        });

        // Priority ordering: tenant-specific first, then by priority
        $record = $query->orderByRaw('CASE WHEN tenant_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('priority', 'desc')
            ->first();

        if (! $record) {
            return null;
        }

        // The PaymentGatewayCredential model uses encrypted:array cast
        // on the credentials column — Laravel handles decryption automatically.
        // No manual Crypt:: calls needed.
        /** @var array<string, mixed> $credentials */
        $credentials = $record->credentials;

        return new Credentials(
            gateway: $gateway,
            profile: $profile,
            credentials: is_array($credentials) ? $credentials : [],
            tenantId: $record->tenant_id,
            label: $record->label,
        );
    }

    public function has(string $gateway, string $profile, array $context = []): bool
    {
        return $this->get($gateway, $profile, $context) !== null;
    }
}

