<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\CustomService\CustomServiceResolver;
use function is_string;

trait CustomServicesResolverAwareTrait
{
    /** @var array<string,?object> */
    private array $customServices = [];

    public function __call(string $name, array $arguments = []): ?object
    {
        $method = lcfirst(ltrim($name, 'get'));

        if (!isset($this->customServices[$method])) {
            $className = $this->servicesMapping()[$method];
            $resolvableType = $this->normalizeResolvableType($className);

            $this->customServices[$method] = (new CustomServiceResolver($resolvableType))
                ->resolve($className);
        }

        return $this->customServices[$method];
    }

    /**
     * @return array<string,class-string>
     */
    abstract protected function servicesMapping(): array;

    private function normalizeResolvableType(string $resolvableType): string
    {
        /** @var list<string> $resolvableTypeParts */
        $resolvableTypeParts = explode('\\', ltrim($resolvableType, '\\'));
        $normalizedResolvableType = end($resolvableTypeParts);

        return is_string($normalizedResolvableType)
            ? $normalizedResolvableType
            : $resolvableType;
    }
}
