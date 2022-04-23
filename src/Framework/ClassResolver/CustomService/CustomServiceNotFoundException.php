<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\CustomService;

use Gacela\Framework\ClassResolver\ClassResolverExceptionTrait;
use RuntimeException;

final class CustomServiceNotFoundException extends RuntimeException
{
    use ClassResolverExceptionTrait;

    /**
     * @param object|class-string $caller
     */
    public function __construct($caller, string $resolvableType)
    {
        parent::__construct($this->buildMessage($caller, $resolvableType));
    }
}
