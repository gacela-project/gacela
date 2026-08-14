<?php

declare(strict_types=1);

namespace Gacela\Psalm\Issue;

use Psalm\Issue\PluginIssue;

/**
 * A `#[Cacheable]` method that never reaches `$this->cached()`, so nothing
 * caches it: the attribute is metadata that `cached()` reads.
 *
 * One class per rule so a consumer can suppress this one on its own:
 *
 * ```xml
 * <PluginIssue name="GacelaCacheableWithoutCachedCall" errorLevel="suppress"/>
 * ```
 */
final class GacelaCacheableWithoutCachedCall extends PluginIssue
{
}
