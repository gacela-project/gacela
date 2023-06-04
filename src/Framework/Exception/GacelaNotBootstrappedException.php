<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use RuntimeException;

final class GacelaNotBootstrappedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Did you forget to call Gacela::bootstrap()?');
    }
}
