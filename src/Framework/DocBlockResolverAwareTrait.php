<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceResolver;
use Gacela\Framework\DocBlockResolver\DocBlockResolver;

trait DocBlockResolverAwareTrait
{
    /** @var array<string,?mixed> */
    private array $customServices = [];

    /**
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters = [])
    {
        if (isset($this->customServices[$method])) {
            return $this->customServices[$method];
        }

        $docBlockResolver = DocBlockResolver::fromCaller($this);
        $resolvable = $docBlockResolver->getDocBlockResolvable($method);

        $resolved = (new DocBlockServiceResolver($resolvable->resolvableType()))
            ->resolve($resolvable->className());

        if ($resolved !== null) {
            return $resolved;
        }

        if ($docBlockResolver->hasParentCallMethod()) {
            /** @psalm-suppress ParentNotFound, MixedAssignment, UndefinedMethod */
            $parentReturn = parent::__call($method, $parameters); // @phpstan-ignore-line
            $this->customServices[$method] = $parentReturn;

            return $parentReturn;
        }

        return null;
    }
}
