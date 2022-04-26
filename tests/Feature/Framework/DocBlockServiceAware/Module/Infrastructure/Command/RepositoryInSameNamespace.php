<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\DocBlockServiceAware\Module\Infrastructure\Command;

use GacelaTest\Feature\Framework\DocBlockServiceAware\Module\Infrastructure\Persistence\FakeDoctrineEntityManager;

final class RepositoryInSameNamespace
{
    private FakeDoctrineEntityManager $entityManager;

    public function __construct(FakeDoctrineEntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function findNameById(int $id): string
    {
        return $this->entityManager->findAdminName($id);
    }
}
