<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use RuntimeException;

final class GacelaNotBootstrappedException extends RuntimeException
{
    public const MESSAGE = 'Did you forget to call Gacela::bootstrap()?';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
