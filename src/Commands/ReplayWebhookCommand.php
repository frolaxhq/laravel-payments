<?php

namespace Frolax\Payment\Commands;

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Contracts\SupportsWebhookVerification;
use Frolax\Payment\Events\WebhookReceived;
use Frolax\Payment\Models\PaymentModel;
use Frolax\Payment\Models\PaymentWebhookEvent;
use Frolax\Payment\GatewayRegistry;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Throwable;

class ReplayWebhookCommand extends Command
{
    protected $signature = 'payments:webhooks:replay {id : The webhook event ID to replay}';

    protected $description = 'Replay a stored webhook event safely (idempotent)';

    public function handle(GatewayRegistry $registry, PaymentLoggerContract $logger): int
    {
        $eventId = $this->argument('id');

        $event = PaymentWebhookEvent::find($eventId);

        if (! $event) {
            $this->error("Webhook event [{$eventId}] not found.");

            return self::FAILURE;
        }

        $this->info("Replaying webhook event [{$eventId}]");
        $this->line("  Gateway: {$event->gateway_name}");
        $this->line("  Event Type: {$event->event_type}");
        $this->line("  Gateway Reference: {$event->gateway_reference}");
        $this->line('  Originally Processed: '.($event->processed ? 'Yes' : 'No'));

        if (! $this->confirm('Do you want to proceed with the replay?', true)) {
            return self::SUCCESS;
        }

        $logger->info('webhook.replay', "Replaying webhook event [{$eventId}]", [
            'gateway' => ['name' => $event->gateway_name],
            'webhook' => ['event_id' => $eventId],
        ]);

        try {
            $driver = $registry->resolve($event->gateway_name);
            $credentialsRepo = app(CredentialsRepositoryContract::class);
            $creds = $credentialsRepo->get($event->gateway_name, config('payments.profile', 'test'));

            if (! $creds) {
                $this->error('No credentials found for this gateway.');

                return self::FAILURE;
            }

            // Create a synthetic request from stored payload
            $jsonBody = json_encode($event->payload ?? []);
            $request = Request::create(
                uri: "/payments/webhook/{$event->gateway_name}",
                method: 'POST',
                server: array_merge(
                    $this->buildServerFromHeaders($event->headers ?? []),
                    ['CONTENT_TYPE' => 'application/json'],
                ),
                content: $jsonBody !== false ? $jsonBody : '{}',
            );

            if (! ($driver instanceof SupportsWebhookVerification)) {
                $this->error('Driver does not support webhook verification.');

                return self::FAILURE;
            }

            $webhookData = $driver->handleWebhook($request, $creds);

            // Update payment status if applicable
            if (config('payments.persistence.enabled') && config('payments.persistence.payments') && $event->gateway_reference && $webhookData->paymentStatus) {
                PaymentModel::where('gateway_reference', $event->gateway_reference)
                    ->where('gateway_name', $event->gateway_name)
                    ->update(['status' => $webhookData->paymentStatus->value]);
            }

            // Re-dispatch event
            event(new WebhookReceived(
                gateway: $event->gateway_name,
                eventType: $event->event_type,
                gatewayReference: $event->gateway_reference,
                signatureValid: $event->signature_valid,
                payload: $event->payload ?? [],
                headers: $event->headers ?? [],
            ));

            // Mark as processed again
            $event->update([
                'processed' => true,
                'processed_at' => now(),
            ]);

            $this->info("Webhook event [{$eventId}] replayed successfully. Status: {$webhookData->canonicalEvent->value}");

            return self::SUCCESS;

        } catch (Throwable $e) {
            dump($e->getMessage(), $e->getTraceAsString());
            $this->error("Replay failed: {$e->getMessage()}");

            $logger->error('webhook.replay.failed', "Webhook replay failed: {$e->getMessage()}", [
                'webhook' => ['event_id' => $eventId],
                'error' => ['message' => $e->getMessage()],
            ]);

            return self::FAILURE;
        }
    }

    protected function buildServerFromHeaders(array $headers): array
    {
        $server = [];
        foreach ($headers as $key => $values) {
            $serverKey = 'HTTP_'.strtoupper(str_replace('-', '_', $key));
            $server[$serverKey] = is_array($values) ? ($values[0] ?? '') : $values;
        }

        return $server;
    }
}
