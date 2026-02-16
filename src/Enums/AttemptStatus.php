<?php

namespace Frolax\Payment\Enums;

enum AttemptStatus: string
{
    case Initiated = 'initiated';
    case Sent = 'sent';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Error = 'error';
}
