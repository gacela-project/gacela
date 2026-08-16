<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Customer\Domain;

use RuntimeException;

use function sprintf;

final class CustomerDirectory
{
    public function __construct(
        private readonly CustomerRepository $repository,
        private readonly string $defaultTier,
    ) {
    }

    public function register(string $reference, string $name, string $countryCode, ?string $tier = null): void
    {
        $this->repository->save(CustomerProfile::fromArray([
            'reference' => $reference,
            'name' => $name,
            'countryCode' => $countryCode,
            'tier' => $tier ?? $this->defaultTier,
        ]));
    }

    public function profileOf(string $reference): CustomerProfile
    {
        $profile = $this->repository->find($reference);

        if (!$profile instanceof CustomerProfile) {
            throw new RuntimeException(sprintf('No customer registered under "%s".', $reference));
        }

        return $profile;
    }

    public function isRegistered(string $reference): bool
    {
        return $this->repository->find($reference) instanceof CustomerProfile;
    }

    public function readCount(): int
    {
        return $this->repository->lookups();
    }
}
