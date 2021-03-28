<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\Config;

use Gacela\AbstractConfig;
use Gacela\ClassResolver\AbstractClassResolver;

final class ConfigResolver extends AbstractClassResolver
{
    /**
     * @throws ConfigNotFoundException
     */
    public function resolve(object $callerClass): AbstractConfig
    {
        /** @var ?AbstractConfig $resolved */
        $resolved = $this->doResolve($callerClass);

        if ($resolved === null) {
            throw new ConfigNotFoundException($this->getClassInfo());
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'Config';
    }
}
