<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes\ModuleC;

use Gacela\Framework\DocBlockResolverAwareTrait;

/**
 * @method Facade getFacade()
 */
final class CommandModuleC
{
    use DocBlockResolverAwareTrait;

    public function execute(): array
    {
        return $this->getFacade()->doSomething();
    }
}
