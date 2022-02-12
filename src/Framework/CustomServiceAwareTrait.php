<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\CustomService\CustomServiceResolver;

trait CustomServiceAwareTrait
{
    /** @var array<string,AbstractCustomService> */
    private array $customServices = [];

    public function __call(string $name, array $arguments = []): AbstractCustomService
    {
        $resolvableType = ltrim($name, 'get');

        if (!isset($this->customServices[$resolvableType])) {
            $this->customServices[$resolvableType] = (new CustomServiceResolver($resolvableType))
                ->resolve($this);
        }

        return $this->customServices[$resolvableType];
    }
}
