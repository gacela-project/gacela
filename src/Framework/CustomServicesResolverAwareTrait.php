<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\CustomService\CustomServiceResolver;

trait CustomServicesResolverAwareTrait
{
    /** @var array<string,?object> */
    private array $customServices = [];

    public function __call(string $name, array $arguments = []): ?object
    {
        $resolvableType = lcfirst(ltrim($name, 'get'));

        if (!isset($this->customServices[$resolvableType])) {
            $className = $this->servicesMapping()[$resolvableType];

            $this->customServices[$resolvableType] = (new CustomServiceResolver($resolvableType))
                ->resolve($className);
        }

        return $this->customServices[$resolvableType];
    }

    /**
     * @return array<string,class-string>
     */
    abstract protected function servicesMapping(): array;
}
