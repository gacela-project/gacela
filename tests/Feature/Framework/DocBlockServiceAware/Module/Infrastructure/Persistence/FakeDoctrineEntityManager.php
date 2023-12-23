<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\DocBlockServiceAware\Module\Infrastructure\Persistence;

final class FakeDoctrineEntityManager
{
    public function findAdminName(int $id): string
    {
        return sprintf('fake-admin(id:%d)', $id);
    }
}
