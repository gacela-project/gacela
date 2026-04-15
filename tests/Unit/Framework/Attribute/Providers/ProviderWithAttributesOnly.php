<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute\Providers;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;

final class ProviderWithAttributesOnly extends AbstractProvider
{
    public function __construct(
        private readonly CallCounter $counter = new CallCounter(),
    ) {
    }

    #[Provides('string_service')]
    public function stringService(): string
    {
        return 'hello';
    }

    /**
     * @return list<int>
     */
    #[Provides('list_service')]
    public function listService(): array
    {
        return [1, 2, 3];
    }

    #[Provides('counted_service')]
    public function countedService(): int
    {
        return $this->counter->bump();
    }

    public function withoutAttribute(): string
    {
        return 'should-not-register';
    }
}
