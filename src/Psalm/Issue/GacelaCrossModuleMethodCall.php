<?php

declare(strict_types=1);

namespace Gacela\Psalm\Issue;

use Psalm\Issue\PluginIssue;

/**
 * A method call on a value whose type belongs to another module.
 *
 * The name is written once, in a type-hint, so this is the crossing
 * `GacelaCrossModuleAccess` cannot see.
 *
 * One class per rule so a consumer can suppress this one on its own:
 *
 * ```xml
 * <PluginIssue name="GacelaCrossModuleMethodCall" errorLevel="suppress"/>
 * ```
 */
final class GacelaCrossModuleMethodCall extends PluginIssue
{
}
