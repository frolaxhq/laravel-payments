<?php

namespace Frolax\Payment\Http\Controllers;

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Contracts\SupportsWebhookVerification;
use Frolax\Payment\Events\WebhookReceived;
use Frolax\Payment\GatewayRegistry;
use Frolax\Payment\Models\PaymentModel;
use Frolax\Payment\Models\PaymentWebhookEvent;
use Frolax\Payment\PaymentConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Throwable;

class WebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $gateway,
        GatewayRegistry $registry,
        PaymentConfig $config,
        PaymentLoggerContract $logger,
    ): Response {
        $logger->info('webhook.received', "Webhook received for gateway [{$gateway}]", [
            'gateway' => ['name' => $gateway],
            'http' => ['request' => ['ip' => $request->ip()]],
        ]);

        $driver = $registry->resolve($gateway);

        // Resolve credentials for signature verification
        $credentialsRepo = app(CredentialsRepositoryContract::class);
        $creds = $credentialsRepo->get($gateway, $config->defaultProfile);

        $signatureValid = true;
        $eventType = null;
        $gatewayReference = null;

        // Verify webhook signature if driver supports it
        if ($driver instanceof SupportsWebhookVerification && $creds) {
            $signatureValid = $driver->verifyWebhookSignature($request, $creds);
            $eventType = $driver->parseWebhookEventType($request);
            $gatewayReference = $driver->parseWebhookGatewayReference($request);

            if (! $signatureValid) {
                $logger->warning('webhook.signature.invalid', "Invalid webhook signature for [{$gateway}]", [
                    'gateway' => ['name' => $gateway],
                ]);

                return response('Invalid signature', 403);
            }
        }

        // Store webhook event (idempotency check)
        $webhookEventId = null;
        if ($config->shouldPersistWebhooks()) {
            // Check if this webhook was already processed (replay safety)
            $existingEvent = null;
            if ($gatewayReference && $eventType) {
                $existingEvent = PaymentWebhookEvent::query()
                    ->where('gateway_name', $gateway)
                    ->where('gateway_reference', $gatewayReference)
                    ->where('event_type', $eventType)
                    ->where('processed', true)
                    ->first();
            }

            if ($existingEvent) {
                $logger->info('webhook.idempotent', "Webhook already processed for [{$gateway}] ref [{$gatewayReference}]", [
                    'gateway' => ['name' => $gateway],
                    'webhook' => ['event_id' => $existingEvent->id],
                ]);

                return response('Already processed', 200);
            }

            // Find associated payment
            $paymentRecord = null;
            if ($gatewayReference) {
                $paymentRecord = PaymentModel::query()
                    ->where('gateway_reference', $gatewayReference)
                    ->where('gateway_name', $gateway)
                    ->first();
            }

            $webhookEventId = (string) Str::ulid();
            PaymentWebhookEvent::query()
                ->create([
                    'id' => $webhookEventId,
                    'gateway_name' => $gateway,
                    'payment_id' => $paymentRecord?->id,
                    'event_type' => $eventType,
                    'gateway_reference' => $gatewayReference,
                    'headers' => $request->headers->all(),
                    'payload' => $request->all(),
                    'signature_valid' => $signatureValid,
                    'processed' => false,
                ]);
        }

        // Dispatch event
        event(new WebhookReceived(
            gateway: $gateway,
            eventType: $eventType,
            gatewayReference: $gatewayReference,
            signatureValid: $signatureValid,
            payload: $request->all(),
            headers: $request->headers->all(),
        ));

        // Process the webhook via the driver's verify method
        if ($creds) {
            try {
                $result = $driver->verify($request, $creds);

                // Update payment status if we have a result and a payment record
                if ($config->shouldPersistPayments() && $gatewayReference) {
                    PaymentModel::query()
                        ->where('gateway_reference', $gatewayReference)
                        ->where('gateway_name', $gateway)
                        ->update(['status' => $result->status->value]);
                }
            } catch (Throwable $e) {
                $logger->error('webhook.processing.failed', "Webhook processing failed: {$e->getMessage()}", [
                    'gateway' => ['name' => $gateway],
                    'error' => ['message' => $e->getMessage()],
                ]);
            }
        }

        // Mark webhook as processed
        if ($webhookEventId && $config->shouldPersistWebhooks()) {
            PaymentWebhookEvent::query()
                ->where('id', $webhookEventId)->update([
                    'processed' => true,
                    'processed_at' => now(),
                ]);
        }

        $logger->info('webhook.processed', "Webhook processed for [{$gateway}]", [
            'gateway' => ['name' => $gateway],
            'webhook' => ['event_id' => $webhookEventId],
        ]);

        return response('OK', 200);
    }
}
