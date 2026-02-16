<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentLink extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('payments.tables.payment_links', 'payment_links');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payload' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'is_single_use' => 'boolean',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentLink $link) {
            if (!$link->slug) {
                $link->slug = Str::random(16);
            }
        });
    }

    // ── Helpers ──

    public function getUrl(): string
    {
        return url(config('payments.routes.prefix', 'payments') . "/link/{$this->slug}");
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->is_single_use && $this->used_at !== null;
    }

    public function isUsable(): bool
    {
        return $this->is_active && !$this->isExpired() && !$this->isUsed();
    }

    public function markUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }
}
