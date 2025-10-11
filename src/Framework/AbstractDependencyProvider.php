<?php

declare(strict_types=1);

namespace Gacela\Framework;

/**
 * @template TConfig of AbstractConfig = AbstractConfig
 *
 * @extends AbstractProvider<TConfig>
 *
 * @deprecated in favor of AbstractProvider. This class will be removed in version 2.0
 */
abstract class AbstractDependencyProvider extends AbstractProvider
{
}
