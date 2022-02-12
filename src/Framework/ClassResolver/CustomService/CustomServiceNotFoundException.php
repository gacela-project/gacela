<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\CustomService;

use Exception;
use Gacela\Framework\ClassResolver\ClassResolverExceptionTrait;

final class CustomServiceNotFoundException extends Exception
{
    use ClassResolverExceptionTrait;

    public function __construct(object $callerClass, string $resolvableType)
    {
        parent::__construct($this->buildMessage($callerClass, $resolvableType));
    }
}
