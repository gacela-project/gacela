<?php

declare(strict_types=1);

namespace Gacela\Psalm\Issue;

use Psalm\Issue\PluginIssue;

/**
 * A class named after a pillar -- `*Facade`, `*Factory`, `*Provider`, `*Config` --
 * that does not extend the matching base class.
 *
 * One class per rule so a consumer can suppress this one on its own:
 *
 * ```xml
 * <PluginIssue name="GacelaSuffixExtends" errorLevel="suppress"/>
 * ```
 */
final class GacelaSuffixExtends extends PluginIssue
{
}
