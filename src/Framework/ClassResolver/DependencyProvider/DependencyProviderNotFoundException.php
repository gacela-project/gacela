<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DependencyProvider;

use Exception;
use Gacela\Framework\ClassResolver\ClassResolverExceptionTrait;

final class DependencyProviderNotFoundException extends Exception
{
    use ClassResolverExceptionTrait;

    public function __construct(object $callerClass)
    {
        parent::__construct($this->buildMessage($callerClass, 'DependencyProvider'));
    }
}
