<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes\ModuleB;

use Gacela\Framework\DocBlockResolverAwareTrait;

/**
 * @method FacadeModuleB getFacade()
 */
final class CommandModuleB
{
    use DocBlockResolverAwareTrait;

    public function execute(): array
    {
        return $this->getFacade()->doSomething();
    }
}
