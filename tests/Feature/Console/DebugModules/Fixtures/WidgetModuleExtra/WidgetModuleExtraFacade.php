<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModuleExtra;

use Gacela\Framework\AbstractFacade;

/**
 * A sibling directory whose name starts with "WidgetModule", so filtering by
 * the WidgetModule *directory* must not pull it in.
 */
final class WidgetModuleExtraFacade extends AbstractFacade
{
}
