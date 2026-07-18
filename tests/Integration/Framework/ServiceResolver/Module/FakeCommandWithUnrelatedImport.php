<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\Module;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * Documents one method and imports an unrelated real class (`AbstractFacade`,
 * which sorts first among the imports). Used to prove that calling an
 * undocumented method throws instead of silently resolving to the first
 * `use` import.
 *
 * @method FakeFacade getFacade()
 */
final class FakeCommandWithUnrelatedImport
{
    use ServiceResolverAwareTrait;

    public function unrelated(): ?AbstractFacade
    {
        return null;
    }
}
