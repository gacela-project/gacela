<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Provider;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

final class ProviderRegisteredEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $providerClass,
        private readonly string $moduleName,
    ) {
    }

    public function providerClass(): string
    {
        return $this->providerClass;
    }

    public function moduleName(): string
    {
        return $this->moduleName;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {providerClass:"%s", moduleName:"%s"}',
            self::class,
            $this->providerClass,
            $this->moduleName,
        );
    }
}
