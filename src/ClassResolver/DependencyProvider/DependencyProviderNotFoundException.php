<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\DependencyProvider;

use Exception;
use Gacela\ClassResolver\ClassInfo;
use Gacela\ClassResolver\ClassResolverExceptionTrait;

final class DependencyProviderNotFoundException extends Exception
{
    use ClassResolverExceptionTrait;

    public function __construct(ClassInfo $callerClassInfo)
    {
        parent::__construct($this->buildMessage($callerClassInfo, 'DependencyProvider'));
    }
}
