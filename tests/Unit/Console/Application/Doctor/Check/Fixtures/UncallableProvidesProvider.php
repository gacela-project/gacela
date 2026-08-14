<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

/**
 * The two faults a *public* `#[Provides]` method can have. Both are accepted by
 * PHP and neither is reported anywhere else.
 */
final class UncallableProvidesProvider extends AbstractProvider
{
    public const NEEDS_ARGUMENT = 'needs-argument';

    public const RETURNS_VOID = 'returns-void';

    public const OPTIONAL_ARGUMENT = 'optional-argument';

    public const FINE = 'fine';

    public function provideModuleDependencies(Container $container): void
    {
    }

    /**
     * Registered, then called with no arguments: `ArgumentCountError`, at
     * whatever point something first resolves the id.
     */
    #[Provides(self::NEEDS_ARGUMENT)]
    public function needsArgument(string $unexpected): string
    {
        return $unexpected;
    }

    /**
     * Registered, and the id answers null.
     */
    #[Provides(self::RETURNS_VOID)]
    public function returnsVoid(): void
    {
    }

    /**
     * Fine: the scanner calls it with none and the default applies, so an
     * optional parameter is not the fault a required one is.
     */
    #[Provides(self::OPTIONAL_ARGUMENT)]
    public function optionalArgument(string $value = 'default'): string
    {
        return $value;
    }

    #[Provides(self::FINE)]
    public function fine(): string
    {
        return 'fine';
    }
}
