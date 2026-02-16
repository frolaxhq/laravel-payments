<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessment extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('payments.tables.risk_assessments', 'payment_risk_assessments');
    }

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'factors' => 'array',
            'metadata' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentModel::class, 'payment_id');
    }

    public function isHighRisk(): bool
    {
        return $this->score >= config('payments.fraud.high_risk_threshold', 70);
    }

    public function isBlocked(): bool
    {
        return $this->decision === 'block';
    }

    public function scopeHighRisk($query)
    {
        return $query->where('score', '>=', config('payments.fraud.high_risk_threshold', 70));
    }

    public function scopeBlocked($query)
    {
        return $query->where('decision', 'block');
    }
}
