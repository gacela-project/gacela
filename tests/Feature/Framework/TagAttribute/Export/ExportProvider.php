<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\TagAttribute\Export;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class ExportProvider extends AbstractProvider
{
    public const EXPORTERS = 'EXPORTERS';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::EXPORTERS, static fn (): array => array_values([...$container->tagged('exporters')]));
    }
}
