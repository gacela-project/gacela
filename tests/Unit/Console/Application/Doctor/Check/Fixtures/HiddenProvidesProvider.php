<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

/**
 * PHP accepts `#[Provides]` anywhere; `ProvidesScanner` reads public methods
 * only. The public one is declared first on purpose, so the check's skip is not
 * the last iteration -- a `continue` reached last is one a `break` can replace.
 */
final class HiddenProvidesProvider extends AbstractProvider
{
    public const VISIBLE = 'VISIBLE_ID';

    public const HIDDEN = 'HIDDEN_ID';

    public const ALSO_HIDDEN = 'ALSO_HIDDEN_ID';

    public function provideModuleDependencies(Container $container): void
    {
    }

    #[Provides(self::VISIBLE)]
    public function visible(): string
    {
        return 'visible';
    }

    #[Provides(self::ALSO_HIDDEN)]
    protected function alsoHidden(): string
    {
        return 'also-hidden';
    }

    #[Provides(self::HIDDEN)]
    private function hidden(): string
    {
        return 'hidden';
    }
}
