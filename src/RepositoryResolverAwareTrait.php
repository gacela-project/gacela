<?php

declare(strict_types=1);

namespace Gacela;

use Gacela\ClassResolver\Repository\RepositoryResolver;

trait RepositoryResolverAwareTrait
{
    private ?AbstractRepository $repository = null;

    public function setRepository(AbstractRepository $repository): self
    {
        $this->repository = $repository;

        return $this;
    }

    protected function getRepository(): AbstractRepository
    {
        if ($this->repository === null) {
            $this->repository = $this->resolveRepository();
        }

        return $this->repository;
    }

    private function resolveRepository(): AbstractRepository
    {
        $resolver = new RepositoryResolver();

        return $resolver->resolve($this);
    }
}
