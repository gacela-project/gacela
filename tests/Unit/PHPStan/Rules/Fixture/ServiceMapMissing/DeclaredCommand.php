<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\ServiceMapMissing;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * The `@method` tag stays for the editor; the attribute is what resolves.
 *
 * @method WalletFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: WalletFacade::class)]
final class DeclaredCommand
{
    use ServiceResolverAwareTrait;
}
