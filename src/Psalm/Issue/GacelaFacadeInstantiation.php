<?php

declare(strict_types=1);

namespace Gacela\Psalm\Issue;

use Psalm\Issue\PluginIssue;

/**
 * A Factory building a Facade with `new`, bypassing the Provider.
 *
 * One class per rule so a consumer can suppress this one on its own:
 *
 * ```xml
 * <PluginIssue name="GacelaFacadeInstantiation" errorLevel="suppress"/>
 * ```
 */
final class GacelaFacadeInstantiation extends PluginIssue
{
}
