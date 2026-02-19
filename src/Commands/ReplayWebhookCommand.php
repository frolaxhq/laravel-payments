<?php

namespace Frolax\Payment\Commands;

use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Events\WebhookReceived;
use Frolax\Payment\GatewayRegistry;
use Frolax\Payment\Models\PaymentWebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

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
            $credentialsRepo = app(\Frolax\Payment\Contracts\CredentialsRepositoryContract::class);
            $creds = $credentialsRepo->get($event->gateway_name, config('payments.profile', 'test'));

            if (! $creds) {
                $this->error('No credentials found for this gateway.');

                return self::FAILURE;
            }

            // Create a synthetic request from stored payload
            $request = Request::create(
                uri: "/payments/webhook/{$event->gateway_name}",
                method: 'POST',
                parameters: $event->payload ?? [],
                server: $this->buildServerFromHeaders($event->headers ?? []),
            );

            $result = $driver->verify($request, $creds);

            // Update payment status if applicable
            if (config('payments.persistence.enabled') && config('payments.persistence.payments') && $event->gateway_reference) {
                \Frolax\Payment\Models\PaymentModel::where('gateway_reference', $event->gateway_reference)
                    ->where('gateway_name', $event->gateway_name)
                    ->update(['status' => $result->status->value]);
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

            $this->info("Webhook event [{$eventId}] replayed successfully. Status: {$result->status->value}");

            return self::SUCCESS;

        } catch (\Throwable $e) {
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
