<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor;

enum CheckStatus: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Error = 'error';
}
