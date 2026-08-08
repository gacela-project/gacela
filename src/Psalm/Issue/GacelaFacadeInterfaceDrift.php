<?php

declare(strict_types=1);

namespace Gacela\Psalm\Issue;

use Psalm\Issue\PluginIssue;

/**
 * A public Facade method missing from the `*FacadeInterface` the Facade
 * implements, which consumers holding the interface cannot reach.
 *
 * One class per rule so a consumer can suppress this one on its own:
 *
 * ```xml
 * <PluginIssue name="GacelaFacadeInterfaceDrift" errorLevel="suppress"/>
 * ```
 */
final class GacelaFacadeInterfaceDrift extends PluginIssue
{
}
