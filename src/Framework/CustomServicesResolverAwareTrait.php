<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\CustomService\CustomServiceResolver;

trait CustomServicesResolverAwareTrait
{
    /** @var array<string,object> */
    private array $customServices = [];

    /**
     * @return object
     */
    public function __call(string $name, array $arguments = [])
    {
        $resolvableType = ltrim($name, 'get');

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
