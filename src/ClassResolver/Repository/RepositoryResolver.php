<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\Repository;

use Gacela\AbstractRepository;
use Gacela\ClassResolver\AbstractClassResolver;

final class RepositoryResolver extends AbstractClassResolver
{
    protected const RESOLVABLE_TYPE = 'Repository';

    public function resolve(object $callerClass): AbstractRepository
    {
        /** @var ?AbstractRepository $resolved */
        $resolved = $this->doResolve($callerClass);

        if ($resolved !== null) {
            return $resolved;
        }

        throw new RepositoryNotFoundException($this->getClassInfo());
    }
}
