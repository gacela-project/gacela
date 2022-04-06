<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Factory;

use Exception;
use Gacela\Framework\ClassResolver\ClassResolverExceptionTrait;

final class FactoryNotFoundException extends Exception
{
    use ClassResolverExceptionTrait;

    /**
     * @param object|class-string $caller
     */
    public function __construct($caller)
    {
        parent::__construct($this->buildMessage($caller, 'Factory'));
    }
}
