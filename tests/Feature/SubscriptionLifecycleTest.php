<?php

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Contracts\SupportsRecurring;
use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CanonicalSubscriptionPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Enums\SubscriptionStatus;
use Frolax\Payment\Events\SubscriptionCancelled;
use Frolax\Payment\Events\SubscriptionCreated;
use Frolax\Payment\Events\SubscriptionPaused;
use Frolax\Payment\Events\SubscriptionResumed;
use Frolax\Payment\GatewayRegistry;
use Frolax\Payment\Models\Subscription;
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

        public function create(CanonicalPayload $p, CredentialsDTO $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: 'GW-'.uniqid());
        }

        public function verify(Request $r, CredentialsDTO $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: 'GW-REF');
        }

        public function setCredentials(CredentialsDTO $c): static
        {
            return $this;
        }

        public function capabilities(): array
        {
            return ['redirect', 'recurring'];
        }

        public function createSubscription(CanonicalSubscriptionPayload $p, CredentialsDTO $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: 'sub_'.uniqid());
        }

        public function cancelSubscription(string $id, CredentialsDTO $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: $id);
        }

        public function pauseSubscription(string $id, CredentialsDTO $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: $id);
        }

        public function resumeSubscription(string $id, CredentialsDTO $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: $id);
        }

        public function updateSubscription(string $id, array $changes, CredentialsDTO $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: $id);
        }

        public function getSubscriptionStatus(string $id, CredentialsDTO $c): GatewayResult
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

    $manager = app(\Frolax\Payment\SubscriptionManager::class);
    $manager->gateway('recurring_mock')->create([
        'plan' => [
            'id' => 'plan_cancel',
            'name' => 'Cancel Plan',
            'money' => ['amount' => 10, 'currency' => 'USD'],
            'interval' => 'monthly',
        ],
    ]);

    $sub = Subscription::latest()->first();

    $result = $manager->gateway('recurring_mock')->cancel($sub->id);
    expect($result->isSuccessful())->toBeTrue();

    $sub->refresh();
    expect($sub->status)->toBe(SubscriptionStatus::Cancelled);
    expect($sub->cancelled_at)->not->toBeNull();
    expect($sub->onGracePeriod())->toBeTrue();

    Event::assertDispatched(SubscriptionCancelled::class);
});

// -------------------------------------------------------
// Pause & Resume
// -------------------------------------------------------

test('subscription lifecycle: pause and resume', function () {
    Event::fake();
    setupRecurringGateway();

    $manager = app(\Frolax\Payment\SubscriptionManager::class);
    $manager->gateway('recurring_mock')->create([
        'plan' => [
            'id' => 'plan_pause',
            'name' => 'Pause Plan',
            'money' => ['amount' => 15, 'currency' => 'USD'],
            'interval' => 'monthly',
        ],
    ]);

    $sub = Subscription::latest()->first();

    $manager->gateway('recurring_mock')->pause($sub->id);
    $sub->refresh();
    expect($sub->status)->toBe(SubscriptionStatus::Paused);
    expect($sub->isPaused())->toBeTrue();
    Event::assertDispatched(SubscriptionPaused::class);

    $manager->gateway('recurring_mock')->resume($sub->id);
    $sub->refresh();
    expect($sub->status)->toBe(SubscriptionStatus::Active);
    expect($sub->isActive())->toBeTrue();
    Event::assertDispatched(SubscriptionResumed::class);
});

// -------------------------------------------------------
// Update
// -------------------------------------------------------

test('subscription lifecycle: update quantity', function () {
    Event::fake();
    setupRecurringGateway();

    $manager = app(\Frolax\Payment\SubscriptionManager::class);
    $manager->gateway('recurring_mock')->create([
        'plan' => [
            'id' => 'plan_upd',
            'name' => 'Updatable Plan',
            'money' => ['amount' => 20, 'currency' => 'USD'],
            'interval' => 'monthly',
        ],
    ]);

    $sub = Subscription::latest()->first();

    $result = $manager->gateway('recurring_mock')->update($sub->id, [
        'quantity' => 5,
    ]);

    expect($result->isSuccessful())->toBeTrue();
    $sub->refresh();
    expect($sub->quantity)->toBe(5);
});

// -------------------------------------------------------
// Throws for non-recurring gateway
// -------------------------------------------------------

test('subscription throws for non-recurring gateway', function () {
    $registry = app(GatewayRegistry::class);
    $registry->register('basic', fn () => new class implements GatewayDriverContract
    {
        public function name(): string
        {
            return 'basic';
        }

        public function create(CanonicalPayload $p, CredentialsDTO $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed);
        }

        public function verify(Request $r, CredentialsDTO $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed);
        }

        public function setCredentials(CredentialsDTO $c): static
        {
            return $this;
        }

        public function capabilities(): array
        {
            return [];
        }
    }, 'Basic');

    config()->set('payments.gateways.basic.test', ['key' => 'k']);

    $manager = app(\Frolax\Payment\SubscriptionManager::class);
    $manager->gateway('basic')->create([
        'plan' => [
            'id' => 'plan_fail',
            'name' => 'Fail',
            'money' => ['amount' => 10, 'currency' => 'USD'],
            'interval' => 'monthly',
        ],
    ]);
})->throws(\Frolax\Payment\Exceptions\UnsupportedCapabilityException::class);
