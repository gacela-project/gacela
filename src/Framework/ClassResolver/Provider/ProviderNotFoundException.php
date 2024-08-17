<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Provider;

use Gacela\Framework\ClassResolver\ClassResolverExceptionTrait;
use RuntimeException;

final class ProviderNotFoundException extends RuntimeException
{
    use ClassResolverExceptionTrait;

    /**
     * @param object|class-string $caller
     */
    public function __construct($caller)
    {
        parent::__construct($this->buildMessage($caller, 'Provider'));
    }
}
