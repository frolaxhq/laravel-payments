<?php

namespace Frolax\Payment\Commands;

use Illuminate\Console\Command;

class PaymentCommand extends Command
{
    public $signature = 'laravel-payments';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
