<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Config;

use Exception;
use Gacela\Framework\ClassResolver\ClassResolverExceptionTrait;

final class ConfigNotFoundException extends Exception
{
    use ClassResolverExceptionTrait;

    /**
     * @param object|class-string $caller
     */
    public function __construct($caller)
    {
        parent::__construct($this->buildMessage($caller, 'Config'));
    }
}
