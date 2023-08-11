<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\FakeModule;

use Gacela\Framework\AbstractFacade;
use RuntimeException;

/**
 * @method FakeModuleFactory getFactory()
 */
abstract class FakeParentFacade extends AbstractFacade
{
    public function overrideByChildMethod(): string
    {
        return 'key from parent';
    }

    public function parentMethod(): string
    {
        return 'parentMethod';
    }

    public function __call(string $name = '', array $arguments = [])
    {
        throw new RuntimeException(sprintf(
            'Method %s::%s does not exist.', static::class, $name
        ));
    }
}
