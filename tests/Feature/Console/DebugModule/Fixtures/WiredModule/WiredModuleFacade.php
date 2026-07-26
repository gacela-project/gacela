<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModule\Fixtures\WiredModule;

use Gacela\Framework\AbstractFacade;

/**
 * Declares a constructor dependency, so the reported dependency tree is not
 * empty.
 */
final class WiredModuleFacade extends AbstractFacade
{
    public function __construct(
        public readonly WiredCollaborator $collaborator,
    ) {
    }
}
