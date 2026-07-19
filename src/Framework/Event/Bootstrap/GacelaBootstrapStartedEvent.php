<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Bootstrap;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

final class GacelaBootstrapStartedEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $appRootDir,
    ) {
    }

    public function appRootDir(): string
    {
        return $this->appRootDir;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {appRootDir:"%s"}',
            self::class,
            $this->appRootDir,
        );
    }
}
