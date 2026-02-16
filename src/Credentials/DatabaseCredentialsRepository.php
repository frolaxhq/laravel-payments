<?php

namespace Frolax\Payment\Credentials;

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\Models\PaymentGatewayCredential;
use Illuminate\Support\Facades\Crypt;

class DatabaseCredentialsRepository implements CredentialsRepositoryContract
{
    public function get(string $gateway, string $profile, array $context = []): ?CredentialsDTO
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
            $q->where(function ($sub) use ($now) {
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

        if (!$record) {
            return null;
        }

        // Decrypt the credentials JSON
        $credentials = $this->decryptCredentials($record->credentials);

        return new CredentialsDTO(
            gateway: $gateway,
            profile: $profile,
            credentials: $credentials,
            tenantId: $record->tenant_id,
            label: $record->label,
        );
    }

    public function has(string $gateway, string $profile, array $context = []): bool
    {
        return $this->get($gateway, $profile, $context) !== null;
    }

    protected function decryptCredentials(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $decrypted = Crypt::decryptString($value);

                return json_decode($decrypted, true) ?: [];
            } catch (\Throwable) {
                // Might already be a JSON string (not encrypted)
                return json_decode($value, true) ?: [];
            }
        }

        if (is_array($value)) {
            return $value;
        }

        return [];
    }
}
