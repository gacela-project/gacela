<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\Config;

use Gacela\AbstractConfig;
use Gacela\ClassResolver\AbstractClassResolver;

final class ConfigResolver extends AbstractClassResolver
{
    protected const RESOLVABLE_TYPE = 'Config';

    /**
     * @throws ConfigNotFoundException
     */
    public function resolve(object $callerClass): AbstractConfig
    {
        /** @var ?AbstractConfig $resolved */
        $resolved = $this->doResolve($callerClass);

        if ($resolved !== null) {
            return $resolved;
        }

        throw new ConfigNotFoundException($this->getClassInfo());
    }
}
