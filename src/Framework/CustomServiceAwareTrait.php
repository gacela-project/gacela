<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\CustomService\CustomServiceResolver;

trait CustomServiceAwareTrait
{
    /** @var array<string,CustomServiceInterface> */
    private array $customServices = [];

    public function __call(string $name, array $arguments = []): CustomServiceInterface
    {
        $normalizedName = (string)preg_replace('/^get/', '', $name);
        $resolvableType = ucfirst($normalizedName);

        if (!isset($this->customServices[$resolvableType])) {
            $this->customServices[$resolvableType] = (new CustomServiceResolver($resolvableType))
                ->resolve($this);
        }

        return $this->customServices[$resolvableType];
    }
}
