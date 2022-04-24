<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceAware\Module\Infrastructure\Persistence;

final class CustomHelloRepository
{
    private FakeDoctrineEntityManager $entityManager;

    public function __construct(FakeDoctrineEntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function findNameName(): string
    {
        return $this->entityManager->findAdminName();
    }
}
