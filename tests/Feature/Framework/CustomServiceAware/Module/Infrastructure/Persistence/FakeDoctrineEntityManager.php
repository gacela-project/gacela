<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceAware\Module\Infrastructure\Persistence;

final class FakeDoctrineEntityManager
{
    public function findAdminName(): string
    {
        return 'fake-admin';
    }
}
