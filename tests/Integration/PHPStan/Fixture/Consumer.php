<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\Fixture;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

#[ServiceMap(method: 'getFacade', className: ConsumerFacade::class)]
final class Consumer
{
    use ServiceResolverAwareTrait;

    public function callsAKnownMethod(): string
    {
        return $this->getFacade()->knownMethod();
    }

    public function callsAMethodThatDoesNotExist(): string
    {
        return $this->getFacade()->typoMethod();
    }
}
