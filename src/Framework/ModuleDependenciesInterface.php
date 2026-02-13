<?php

declare(strict_types=1);

namespace Gacela\Framework;

/**
 * Interface for declaring explicit module dependencies.
 *
 * This enables dependency graph visualization and circular dependency detection.
 */
interface ModuleDependenciesInterface
{
    /**
     * Declare the module dependencies.
     *
     * Returns an array of fully-qualified class names of facades
     * that this module depends on.
     *
     * @return array<class-string<AbstractFacade<AbstractFactory<AbstractConfig>>>> List of facade class names
     */
    public function dependencies(): array;
}
