<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Config;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class ConfigResolver extends AbstractClassResolver
{
    /**
     * @throws ConfigNotFoundException
     */
    public function resolve(object $callerClass): AbstractConfig
    {
        /** @var ?AbstractConfig $resolved */
        $resolved = $this->doResolve($callerClass);

        if (null === $resolved) {
            throw new ConfigNotFoundException($callerClass);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'Config';
    }
}
