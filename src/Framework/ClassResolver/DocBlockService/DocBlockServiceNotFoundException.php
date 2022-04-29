<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

use Gacela\Framework\ClassResolver\ClassResolverExceptionTrait;
use RuntimeException;

final class DocBlockServiceNotFoundException extends RuntimeException
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
