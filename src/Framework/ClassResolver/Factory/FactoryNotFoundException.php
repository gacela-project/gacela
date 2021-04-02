<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Factory;

use Exception;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassResolverExceptionTrait;

final class FactoryNotFoundException extends Exception
{
    use ClassResolverExceptionTrait;

    public function __construct(ClassInfo $callerClassInfo)
    {
        parent::__construct($this->buildMessage($callerClassInfo, 'Factory'));
    }
}
