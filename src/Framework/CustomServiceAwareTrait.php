<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\CustomService\CustomServiceResolver;

trait CustomServiceAwareTrait
{
    /** @var array<string,AbstractCustomService> */
    private array $flexibleServices = [];

    public function __call(string $name, array $arguments = []): AbstractCustomService
    {
        $resolvableType = ltrim($name, 'get');

        if (!isset($this->flexibleServices[$resolvableType])) {
            $this->flexibleServices[$resolvableType] = (new CustomServiceResolver($resolvableType))
                ->resolve($this);
        }

        return $this->flexibleServices[$resolvableType];
    }
}
