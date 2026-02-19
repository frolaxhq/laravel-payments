<?php

namespace Frolax\Payment\Services;

use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Models\PaymentModel;
use Frolax\Payment\Models\PaymentRefund;
use Frolax\Payment\Models\Subscription;

class RevenueAnalytics
{
    /**
     * Get revenue summary for a period.
     */
    public function summary(?string $from = null, ?string $to = null, ?string $gateway = null): array
    {
        $query = PaymentModel::where('status', PaymentStatus::Completed);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }
        if ($gateway) {
            $query->where('gateway_name', $gateway);
        }

        $payments = $query->get();

        $refunds = PaymentRefund::where('status', 'completed')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->sum('amount');

        return [
            'gross_revenue' => round($payments->sum('amount'), 2),
            'refund_total' => round($refunds, 2),
            'net_revenue' => round($payments->sum('amount') - $refunds, 2),
            'payment_count' => $payments->count(),
            'average_payment' => $payments->count() > 0 ? round($payments->avg('amount'), 2) : 0,
            'by_currency' => $payments->groupBy('currency')->map(fn ($group) => [
                'total' => round($group->sum('amount'), 2),
                'count' => $group->count(),
            ])->toArray(),
            'by_gateway' => $payments->groupBy('gateway_name')->map(fn ($group) => [
                'total' => round($group->sum('amount'), 2),
                'count' => $group->count(),
            ])->toArray(),
        ];
    }

    /**
     * Get Monthly Recurring Revenue (MRR).
     */
    public function mrr(): float
    {
        return round(Subscription::active()->sum('amount'), 2);
    }

    /**
     * Get Annual Recurring Revenue (ARR).
     */
    public function arr(): float
    {
        return round($this->mrr() * 12, 2);
    }

    /**
     * Get gateway success rates.
     */
    public function gatewaySuccessRates(?string $from = null, ?string $to = null): array
    {
        $query = PaymentModel::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $grouped = $query->get()->groupBy('gateway_name');

        return $grouped->map(function ($payments, $gateway) {
            $total = $payments->count();
            $successful = $payments->where('status', PaymentStatus::Completed)->count();

            return [
                'gateway' => $gateway,
                'total' => $total,
                'successful' => $successful,
                'failed' => $total - $successful,
                'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            ];
        })->values()->toArray();
    }

    /**
     * Get conversion funnel (created → completed).
     */
    public function conversionFunnel(?string $from = null, ?string $to = null): array
    {
        $query = PaymentModel::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $all = $query->get();
        $total = $all->count();

        $statuses = [
            'created' => $total,
            'pending' => $all->where('status', PaymentStatus::Pending)->count(),
            'processing' => $all->where('status', PaymentStatus::Processing)->count(),
            'completed' => $all->where('status', PaymentStatus::Completed)->count(),
            'failed' => $all->where('status', PaymentStatus::Failed)->count(),
            'cancelled' => $all->where('status', PaymentStatus::Cancelled)->count(),
        ];

        return array_map(fn ($count) => [
            'count' => $count,
            'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
        ], $statuses);
    }
}
