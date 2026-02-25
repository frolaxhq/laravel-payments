<?php

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Contracts\SupportsRecurring;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\Payload;
use Frolax\Payment\Data\SubscriptionPayload;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Enums\SubscriptionStatus;
use Frolax\Payment\Events\SubscriptionCancelled;
use Frolax\Payment\Events\SubscriptionCreated;
use Frolax\Payment\Events\SubscriptionPaused;
use Frolax\Payment\Events\SubscriptionResumed;
use Frolax\Payment\GatewayRegistry;
use Frolax\Payment\Models\Subscription;
use Frolax\Payment\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

function createRecurringDriver(): GatewayDriverContract&SupportsRecurring
{
    return new class implements GatewayDriverContract, SupportsRecurring
    {
        public function name(): string
        {
            return 'recurring_mock';
        }

        public function create(Payload $p, Credentials $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: 'GW-'.uniqid());
        }

        public function verify(Request $r, Credentials $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: 'GW-REF');
        }

        public function setCredentials(Credentials $c): static
        {
            return $this;
        }

        public function capabilities(): array
        {
            return ['redirect', 'recurring'];
        }

        public function createSubscription(SubscriptionPayload $p, Credentials $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: 'sub_'.uniqid());
        }

        public function cancelSubscription(string $id, Credentials $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: $id);
        }

        public function pauseSubscription(string $id, Credentials $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: $id);
        }

        public function resumeSubscription(string $id, Credentials $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: $id);
        }

        public function updateSubscription(string $id, array $changes, Credentials $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: $id);
        }

        public function getSubscriptionStatus(string $id, Credentials $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: $id);
        }
    };
}

function setupRecurringGateway(): void
{
    $registry = app(GatewayRegistry::class);
    $registry->register('recurring_mock', fn () => createRecurringDriver(), 'Mock Recurring', ['recurring']);
    config()->set('payments.gateways.recurring_mock.test', ['key' => 'test_key']);
}

// -------------------------------------------------------
// Subscribe
// -------------------------------------------------------

test('subscription lifecycle: create subscription', function () {
    Event::fake();
    setupRecurringGateway();

    $manager = app(\Frolax\Payment\SubscriptionManager::class);
    $result = $manager->gateway('recurring_mock')->create([
        'plan' => [
            'id' => 'plan_pro',
            'name' => 'Pro Plan',
            'money' => ['amount' => 49.99, 'currency' => 'USD'],
            'interval' => 'monthly',
        ],
        'customer' => [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ],
    ]);

    expect($result)->toBeInstanceOf(GatewayResult::class);
    expect($result->isSuccessful())->toBeTrue();

    $sub = Subscription::latest()->first();
    expect($sub)->not->toBeNull();
    expect($sub->plan_id)->toBe('plan_pro');
    expect($sub->customer_email)->toBe('jane@example.com');
    expect($sub->status)->toBe(SubscriptionStatus::Active);
    expect($sub->gateway_subscription_id)->toStartWith('sub_');

    Event::assertDispatched(SubscriptionCreated::class);
});

test('subscription lifecycle: create with trial', function () {
    Event::fake();
    setupRecurringGateway();

    $manager = app(\Frolax\Payment\SubscriptionManager::class);
    $manager->gateway('recurring_mock')->create([
        'plan' => [
            'id' => 'plan_trial',
            'name' => 'Trial Plan',
            'money' => ['amount' => 29.99, 'currency' => 'USD'],
            'interval' => 'monthly',
        ],
        'trial_days' => 14,
    ]);

    $sub = Subscription::latest()->first();
    expect($sub->status)->toBe(SubscriptionStatus::Trialing);
    expect($sub->trial_ends_at)->not->toBeNull();
    expect($sub->onTrial())->toBeTrue();
});

// -------------------------------------------------------
// Cancel
// -------------------------------------------------------

test('subscription lifecycle: cancel subscription', function () {
    Event::fake();
    setupRecurringGateway();

    $payment = app(Payment::class);
    $payment->gateway('recurring_mock')->subscribe([
        'plan' => [
            'id' => 'plan_cancel',
            'name' => 'Cancel Plan',
            'money' => ['amount' => 10, 'currency' => 'USD'],
            'interval' => 'monthly',
        ],
    ]);

    $subscription = Subscription::latest()->first();

    $res = $payment->gateway('recurring_mock')->cancelSubscription($subscription->id);
    expect($res->status)->toBe(PaymentStatus::Completed);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Cancelled);
    expect($subscription->cancelled_at)->not->toBeNull();
    expect($subscription->onGracePeriod())->toBeTrue();

    Event::assertDispatched(SubscriptionCancelled::class);
});

// -------------------------------------------------------
// Pause & Resume
// -------------------------------------------------------

test('subscription lifecycle: pause and resume', function () {
    Event::fake();
    setupRecurringGateway();

    $payment = app(Payment::class);
    $payment->gateway('recurring_mock')->subscribe([
        'plan' => [
            'id' => 'plan_pause',
            'name' => 'Pause Plan',
            'money' => ['amount' => 15, 'currency' => 'USD'],
            'interval' => 'monthly',
        ],
    ]);

    $subscription = Subscription::latest()->first();

    $res = $payment->gateway('recurring_mock')->pauseSubscription($subscription->id);
    expect($res->status)->toBe(PaymentStatus::Completed);
    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Paused);
    expect($subscription->isPaused())->toBeTrue();
    Event::assertDispatched(SubscriptionPaused::class);

    $res2 = $payment->gateway('recurring_mock')->resumeSubscription($subscription->id);
    expect($res2->status)->toBe(PaymentStatus::Completed);
    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Active);
    expect($subscription->isActive())->toBeTrue();
    Event::assertDispatched(SubscriptionResumed::class);
});

// -------------------------------------------------------
// Update
// -------------------------------------------------------

test('subscription lifecycle: update quantity', function () {
    Event::fake();
    setupRecurringGateway();

    $payment = app(Payment::class);
    $payment->gateway('recurring_mock')->subscribe([
        'plan' => [
            'id' => 'plan_upd',
            'name' => 'Updatable Plan',
            'money' => ['amount' => 20, 'currency' => 'USD'],
            'interval' => 'monthly',
        ],
    ]);

    $subscription = Subscription::latest()->first();

    $res = $payment->gateway('recurring_mock')->updateSubscription($subscription->id, [
        'quantity' => 5,
        'plan_id' => 'new_plan',
    ]);

    expect($res->status)->toBe(PaymentStatus::Completed);
    $subscription->refresh();
    expect($subscription->quantity)->toBe(5);
    expect($subscription->plan_id)->toBe('new_plan');
});

// -------------------------------------------------------
// Throws for non-recurring gateway
// -------------------------------------------------------

test('subscription throws for non-recurring gateway on all endpoints', function () {
    $nonRecurringDriver = \Mockery::mock(GatewayDriverContract::class);
    $nonRecurringDriver->shouldReceive('name')->andReturn('simple');

    $registry = app(GatewayRegistry::class);
    $registry->register('simple', fn () => $nonRecurringDriver);

    $payment = app(\Frolax\Payment\Payment::class)->gateway('simple');

    expect(fn () => $payment->subscribe([
        'plan' => ['id' => '1', 'name' => 'A', 'interval' => 'monthly', 'interval_count' => 1, 'money' => ['amount' => 10, 'currency' => 'USD']],
    ]))->toThrow(\Frolax\Payment\Exceptions\UnsupportedCapabilityException::class);

    expect(fn () => $payment->pauseSubscription('x'))
        ->toThrow(\Frolax\Payment\Exceptions\UnsupportedCapabilityException::class);

    expect(fn () => $payment->resumeSubscription('x'))
        ->toThrow(\Frolax\Payment\Exceptions\UnsupportedCapabilityException::class);

    expect(fn () => $payment->cancelSubscription('x'))
        ->toThrow(\Frolax\Payment\Exceptions\UnsupportedCapabilityException::class);

    expect(fn () => $payment->updateSubscription('x', []))
        ->toThrow(\Frolax\Payment\Exceptions\UnsupportedCapabilityException::class);
});
