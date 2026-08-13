<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\ServiceMapMissing;

use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * @method WalletFacade getFacade()
 */
final class DocBlockCommand
{
    use ServiceResolverAwareTrait;
}
