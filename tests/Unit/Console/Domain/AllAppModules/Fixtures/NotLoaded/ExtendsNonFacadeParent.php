<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\AllAppModules\Fixtures\NotLoaded;

/**
 * Referenced only by name, as a string. Its parent chain ends without reaching
 * AbstractFacade, which discovery can read from source, so neither this class
 * nor its parent should be loaded by it.
 */
final class ExtendsNonFacadeParent extends NonFacadeParent
{
}
