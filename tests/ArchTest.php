<?php

// Architecture tests

arch('contracts are interfaces')
    ->expect('Frolax\Payment\Contracts')
    ->toBeInterfaces();

arch('DTOs are readonly final classes')
    ->expect('Frolax\Payment\Data')
    ->toBeFinal()
    ->toBeReadonly();

arch('enums are backed enums')
    ->expect('Frolax\Payment\Enums')
    ->toBeEnums();

arch('exceptions extend RuntimeException or Exception')
    ->expect('Frolax\Payment\Exceptions')
    ->toExtend(RuntimeException::class);

arch('events use Dispatchable trait')
    ->expect('Frolax\Payment\Events')
    ->toUseTrait(Illuminate\Foundation\Events\Dispatchable::class);

arch('models extend Eloquent Model')
    ->expect('Frolax\Payment\Models')
    ->toExtend(Illuminate\Database\Eloquent\Model::class);

arch('core does not reference gateway-specific names')
    ->expect('Frolax\Payment')
    ->not->toUse([
        'Stripe',
        'Bkash',
        'PayPal',
    ]);

// ── New architecture rules from rebuild plan ──

arch('DTO layer has no side effects (no DB, no HTTP, no events)')
    ->expect('Frolax\Payment\Data')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Support\Facades\Http',
        'Illuminate\Support\Facades\Event',
    ]);

arch('pipeline steps do not depend on contracts layer')
    ->expect('Frolax\Payment\Pipeline\Steps')
    ->not->toUse([
        'Frolax\Payment\Contracts\SupportsRecurring',
        'Frolax\Payment\Contracts\SupportsRefund',
        'Frolax\Payment\Contracts\SupportsStatusQuery',
    ]);

arch('models are not used inside DTOs or Contracts')
    ->expect('Frolax\Payment\Data')
    ->not->toUse(['Frolax\Payment\Models']);

arch('contracts do not use models')
    ->expect('Frolax\Payment\Contracts')
    ->not->toUse(['Frolax\Payment\Models']);

arch('no static mutable state in testing fakes')
    ->expect('Frolax\Payment\Testing')
    ->not->toUse(['static::']);

arch('PaymentContext is final')
    ->expect('Frolax\Payment\Pipeline\PaymentContext')
    ->toBeFinal();

arch('enums do not depend on models')
    ->expect('Frolax\Payment\Enums')
    ->not->toUse(['Frolax\Payment\Models']);

arch('credentials repository does not use Crypt facade')
    ->expect('Frolax\Payment\Credentials')
    ->not->toUse(['Illuminate\Support\Facades\Crypt']);
