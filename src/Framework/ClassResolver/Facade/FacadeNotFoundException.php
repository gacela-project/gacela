<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Facade;

use Exception;
use Gacela\Framework\ClassResolver\ClassResolverExceptionTrait;

final class FacadeNotFoundException extends Exception
{
    use ClassResolverExceptionTrait;

    /**
     * @param object|class-string $caller
     */
    public function __construct($caller)
    {
        parent::__construct($this->buildMessage($caller, 'Facade'));
    }
}
