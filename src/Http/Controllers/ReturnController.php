<?php

namespace Frolax\Payment\Http\Controllers;

use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class ReturnController extends Controller
{
    public function __invoke(
        Request $request,
        string $gateway,
        Payment $payment,
        PaymentLoggerContract $logger,
    ): RedirectResponse {
        $logger->info('return.received', "Return callback received for gateway [$gateway]", [
            'gateway' => ['name' => $gateway],
            'http' => ['request' => ['ip' => $request->ip()]],
        ]);

        try {
            $result = $payment->gateway($gateway)->verifyFromRequest($request);

            $logger->info('return.verified', "Return verification completed for [$gateway]", [
                'gateway' => ['name' => $gateway],
                'verification' => ['status' => $result->status->value, 'paid' => $result->isSuccessful()],
            ]);
        } catch (Throwable $e) {
            $logger->error('return.failed', "Return verification failed: {$e->getMessage()}", [
                'gateway' => ['name' => $gateway],
                'error' => ['message' => $e->getMessage()],
            ]);
        }

        // Redirect to the app's configured return URL or home
        return redirect()->to($request->query('redirect', '/'));
    }
}
