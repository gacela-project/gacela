<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\DocBlockServiceAware\Module\Infrastructure\Persistence;

final class CustomHelloRepository
{
    public function __construct(
        private FakeDoctrineEntityManager $entityManager,
    ) {
    }

    public function findNameById(int $id): string
    {
        return $this->entityManager->findAdminName($id);
    }
}
