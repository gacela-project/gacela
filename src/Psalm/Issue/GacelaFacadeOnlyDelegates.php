<?php

declare(strict_types=1);

namespace Gacela\Psalm\Issue;

use Psalm\Issue\PluginIssue;

/**
 * Logic inside a Facade method.
 *
 * A Facade method is a name for something the Factory does; logic living in it
 * is logic no other module can reach and no test can address directly.
 *
 * One class per rule so a consumer can suppress this one on its own:
 *
 * ```xml
 * <PluginIssue name="GacelaFacadeOnlyDelegates" errorLevel="suppress"/>
 * ```
 */
final class GacelaFacadeOnlyDelegates extends PluginIssue
{
}
