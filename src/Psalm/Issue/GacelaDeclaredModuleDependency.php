<?php

declare(strict_types=1);

namespace Gacela\Psalm\Issue;

use Psalm\Issue\PluginIssue;

/**
 * A dependency between modules that the project's own rules file forbids.
 *
 * One class per rule so a consumer can suppress this one on its own:
 *
 * ```xml
 * <PluginIssue name="GacelaDeclaredModuleDependency" errorLevel="suppress"/>
 * ```
 */
final class GacelaDeclaredModuleDependency extends PluginIssue
{
}
