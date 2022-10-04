<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolverAware\Service;

final class FakeService implements FakeServiceInterface
{
    public function getName(): string
    {
        return 'fake-service.name';
    }
}
