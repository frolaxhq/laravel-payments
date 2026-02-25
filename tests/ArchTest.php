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
