<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Config;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class ConfigResolver extends AbstractClassResolver
{
    public const TYPE = 'Config';

    /**
     * @param object|class-string $caller
     *
     * @throws ConfigNotFoundException
     */
    public function resolve($caller): AbstractConfig
    {
        /** @var ?AbstractConfig $resolved */
        $resolved = $this->doResolve($caller);

        if ($resolved === null) {
            throw new ConfigNotFoundException($caller);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return self::TYPE;
    }
}
