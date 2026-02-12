<?php

namespace Frolax\Payment\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Frolax\Payment\Payment
 */
class Payment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Frolax\Payment\Payment::class;
    }
}
