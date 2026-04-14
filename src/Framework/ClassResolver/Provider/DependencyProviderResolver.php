<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Provider;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\ClassInfo;

/**
 * @psalm-suppress DeprecatedClass
 */
final class DependencyProviderResolver extends AbstractClassResolver
{
    public const TYPE = 'DependencyProvider';

    /**
     * @param object|class-string $caller
     */
    public function resolve(object|string $caller): ?AbstractProvider
    {
        /** @var ?AbstractDependencyProvider $resolved */
        $resolved = $this->doResolve($caller);

        if ($resolved instanceof AbstractDependencyProvider) {
            trigger_deprecation(
                'gacela-project/gacela',
                '1.8',
                "`%s` is deprecated and will be removed in version 2.0.\nUse `%s` instead. Where? Check your module `%s`",
                AbstractDependencyProvider::class,
                AbstractProvider::class,
                ClassInfo::from($caller, self::TYPE)->getModuleName(),
            );
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return self::TYPE;
    }
}
