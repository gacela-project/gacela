<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\Config;

use Gacela\AbstractConfig;
use Gacela\ClassResolver\AbstractClassResolver;

final class ConfigResolver extends AbstractClassResolver
{
    protected const RESOLVABLE_TYPE = 'Config';

    /**
     * @param object|string $callerClass
     *
     * @throws ConfigNotFoundException
     */
    public function resolve($callerClass): AbstractConfig
    {
        /** @var ?AbstractConfig $resolved */
        $resolved = $this->doResolve($callerClass);

        if ($resolved !== null) {
            return $resolved;
        }

        throw new ConfigNotFoundException($this->getClassInfo());
    }
}
