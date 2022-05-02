<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes\ModuleA;

use Gacela\Framework\DocBlockResolverAwareTrait;

/**
 * @method FacaModuleA getFacade()
 */
final class CommandModuleA
{
    use DocBlockResolverAwareTrait;

    public function execute(): array
    {
        return $this->getFacade()->doSomething();
    }
}
