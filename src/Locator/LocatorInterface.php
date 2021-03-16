<?php

declare(strict_types=1);

namespace Gacela\Locator;

interface LocatorInterface
{
    /**
     * @return mixed
     */
    public function locate(string $bundle);
}
