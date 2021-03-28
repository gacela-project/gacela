<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\Repository;

use Exception;
use Gacela\ClassResolver\ClassInfo;
use Gacela\ClassResolver\ClassResolverExceptionTrait;

final class RepositoryNotFoundException extends Exception
{
    use ClassResolverExceptionTrait;

    public function __construct(ClassInfo $callerClassInfo)
    {
        parent::__construct($this->buildMessage($callerClassInfo, 'Repository'));
    }
}
