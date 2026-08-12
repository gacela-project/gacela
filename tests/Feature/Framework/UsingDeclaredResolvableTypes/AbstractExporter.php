<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingDeclaredResolvableTypes;

use Gacela\Framework\ConfigResolverAwareTrait;

/**
 * The base a declared kind names. The framework ships none for project kinds --
 * that is the point of declaring one -- but it carries the same config seam the
 * pillars do.
 *
 * @template TConfig of \Gacela\Framework\AbstractConfig
 *
 * @use ConfigResolverAwareTrait<TConfig>
 */
abstract class AbstractExporter
{
    /** @use ConfigResolverAwareTrait<\Gacela\Framework\AbstractConfig> */
    use ConfigResolverAwareTrait;
}
