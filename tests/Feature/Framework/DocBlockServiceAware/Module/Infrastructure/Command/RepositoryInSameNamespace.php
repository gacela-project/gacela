<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\DocBlockServiceAware\Module\Infrastructure\Command;

use GacelaTest\Feature\Framework\DocBlockServiceAware\Module\Infrastructure\Persistence\FakeDoctrineEntityManager;

final class RepositoryInSameNamespace
{
    public function __construct(
        private readonly FakeDoctrineEntityManager $entityManager,
    ) {
    }

    public function findNameById(int $id): string
    {
        return $this->entityManager->findAdminName($id);
    }
}
