<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\Repository;

use Gacela\AbstractRepository;
use Gacela\ClassResolver\AbstractClassResolver;

final class RepositoryResolver extends AbstractClassResolver
{
    public function resolve(object $callerClass): AbstractRepository
    {
        /** @var ?AbstractRepository $resolved */
        $resolved = $this->doResolve($callerClass);

        if ($resolved === null) {
            throw new RepositoryNotFoundException($this->getClassInfo());
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'Repository';
    }
}
