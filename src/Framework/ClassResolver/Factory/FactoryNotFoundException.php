<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Factory;

use Exception;
use Gacela\Framework\ClassResolver\ClassResolverExceptionTrait;

final class FactoryNotFoundException extends Exception
{
    use ClassResolverExceptionTrait;

    public function __construct(object $callerClass)
    {
        parent::__construct($this->buildMessage($callerClass, 'Factory'));
    }
}
