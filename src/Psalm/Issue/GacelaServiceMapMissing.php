<?php

declare(strict_types=1);

namespace Gacela\Psalm\Issue;

use Psalm\Issue\PluginIssue;

/**
 * A pillar accessor documented with `@method` and not declared with
 * `#[ServiceMap]` -- resolution that 3.0 removes.
 *
 * One class per rule so a consumer can suppress this one on its own:
 *
 * ```xml
 * <PluginIssue name="GacelaServiceMapMissing" errorLevel="suppress"/>
 * ```
 */
final class GacelaServiceMapMissing extends PluginIssue
{
}
