<?php

namespace Frolax\Payment\Services;

use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\Models\BlocklistEntry;
use Frolax\Payment\Models\PaymentModel;
use Frolax\Payment\Models\RiskAssessment;

class RiskScorer
{
    /**
     * Assess fraud risk for a payment.
     */
    public function assess(CanonicalPayload $payload): RiskAssessment
    {
        $score = 0;
        $factors = [];

        // Check blocklist
        if ($payload->customer?->email && BlocklistEntry::isBlocked('email', $payload->customer->email)) {
            $score += 100;
            $factors[] = ['type' => 'blocklist', 'detail' => 'Email is blocklisted'];
        }

        if ($payload->context?->ip && BlocklistEntry::isBlocked('ip', $payload->context->ip)) {
            $score += 100;
            $factors[] = ['type' => 'blocklist', 'detail' => 'IP is blocklisted'];
        }

        // Velocity checks
        if ($payload->customer?->email) {
            $recentCount = PaymentModel::where('metadata->customer_email', $payload->customer->email)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($recentCount > config('payments.fraud.velocity.max_per_hour', 10)) {
                $score += 30;
                $factors[] = ['type' => 'velocity', 'detail' => "High velocity: {$recentCount} payments in last hour"];
            }
        }

        // High amount check
        $highAmountThreshold = config('payments.fraud.high_amount_threshold', 10000);
        if ($payload->money->amount > $highAmountThreshold) {
            $score += 15;
            $factors[] = ['type' => 'amount', 'detail' => "Amount exceeds threshold: {$payload->money->amount} > {$highAmountThreshold}"];
        }

        // Determine decision
        $decision = match (true) {
            $score >= config('payments.fraud.block_threshold', 80) => 'block',
            $score >= config('payments.fraud.review_threshold', 50) => 'review',
            default => 'allow',
        };

        return RiskAssessment::create([
            'score' => min($score, 100),
            'factors' => $factors,
            'decision' => $decision,
            'ip_address' => $payload->context?->ip,
            'email' => $payload->customer?->email,
        ]);
    }
}
