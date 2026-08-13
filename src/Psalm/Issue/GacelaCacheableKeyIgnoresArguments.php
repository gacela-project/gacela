<?php

declare(strict_types=1);

namespace Gacela\Psalm\Issue;

use Psalm\Issue\PluginIssue;

/**
 * A `#[Cacheable]` key that never mentions the arguments, on a method that has
 * them, so every call shares one entry and the first result is served to all.
 *
 * One class per rule so a consumer can suppress this one on its own:
 *
 * ```xml
 * <PluginIssue name="GacelaCacheableKeyIgnoresArguments" errorLevel="suppress"/>
 * ```
 */
final class GacelaCacheableKeyIgnoresArguments extends PluginIssue
{
}
