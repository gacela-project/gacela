<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Provider;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\ClassInfo;

use function sprintf;

use const E_USER_DEPRECATED;

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
            // Emitted directly rather than through `trigger_deprecation()`: that function comes
            // from symfony/deprecation-contracts, which is not a runtime dependency of gacela, so
            // guarding on it silenced this notice for everyone without symfony installed. The
            // message keeps the contract's "Since <package> <version>: " format, so deprecation
            // collectors group it exactly as before.
            @trigger_error(
                sprintf(
                    "Since gacela-project/gacela 1.8: `%s` is deprecated and will be removed in version 2.0.\n"
                    . 'Use `%s` instead. Where? Check your module `%s`',
                    AbstractDependencyProvider::class,
                    AbstractProvider::class,
                    ClassInfo::from($caller, self::TYPE)->getModuleName(),
                ),
                E_USER_DEPRECATED,
            );
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return self::TYPE;
    }
}
