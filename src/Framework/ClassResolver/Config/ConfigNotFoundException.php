<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Config;

use Exception;
use Gacela\Framework\ClassResolver\ClassResolverExceptionTrait;

final class ConfigNotFoundException extends Exception
{
    use ClassResolverExceptionTrait;

    public function __construct(object $callerClass)
    {
        parent::__construct($this->buildMessage($callerClass, 'Config'));
    }
}
