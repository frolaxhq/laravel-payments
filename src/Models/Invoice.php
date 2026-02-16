<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('payments.tables.invoices', 'payment_invoices');
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'metadata' => 'array',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentModel::class, 'payment_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    // ── State ──

    public function isPaid(): bool
    {
        return $this->status === 'paid' && $this->paid_at !== null;
    }

    public function isOverdue(): bool
    {
        return $this->status === 'unpaid' && $this->due_at?->isPast();
    }

    public function markPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function markVoided(): void
    {
        $this->update([
            'status' => 'voided',
            'voided_at' => now(),
        ]);
    }

    // ── Scopes ──

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'unpaid')
            ->where('due_at', '<', now());
    }

    public function scopeForCustomer($query, string $email)
    {
        return $query->where('customer_email', $email);
    }
}
