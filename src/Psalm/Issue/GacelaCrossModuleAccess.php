<?php

declare(strict_types=1);

namespace Gacela\Psalm\Issue;

use Psalm\Issue\PluginIssue;

/**
 * A reference from one module into another that does not go through a Facade.
 *
 * One class per rule so a consumer can suppress this one on its own:
 *
 * ```xml
 * <PluginIssue name="GacelaCrossModuleAccess" errorLevel="suppress"/>
 * ```
 */
final class GacelaCrossModuleAccess extends PluginIssue
{
}
