<?php

namespace Frolax\Payment\Http\Controllers;

use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Events\PaymentCancelled;
use Frolax\Payment\Models\PaymentModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CancelController extends Controller
{
    public function __invoke(
        Request $request,
        string $gateway,
        PaymentLoggerContract $logger,
    ): RedirectResponse {
        $logger->info('cancel.received', "Cancel callback received for gateway [$gateway]", [
            'gateway' => ['name' => $gateway],
            'http' => ['request' => ['ip' => $request->ip()]],
        ]);

        // Try to find and update the payment record
        $orderId = $request->query('order_id');
        if ($orderId && config('payments.persistence.enabled') && config('payments.persistence.payments')) {
            $paymentRecord = PaymentModel::query()
                ->where('order_id', $orderId)
                ->where('gateway_name', $gateway)
                ->where('status', PaymentStatus::Pending)
                ->first();

            if ($paymentRecord) {
                $paymentRecord->update(['status' => PaymentStatus::Cancelled->value]);

                event(new PaymentCancelled(
                    paymentId: $paymentRecord->id,
                    gateway: $gateway,
                ));
            }
        }

        return redirect()->to($request->query('redirect', '/'));
    }
}
