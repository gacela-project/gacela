<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

/**
 * How the container will supply a node of a dependency tree.
 *
 * Distinct from {@see ParameterStatus}, which categorises a single constructor
 * parameter from its type hint. This one is answered by the container itself:
 * `provides()` reports whether it owns something for the id, and `getBindings()`
 * separates a declared binding from an instance that merely ended up stored.
 */
enum ProvisionStatus: string
{
    case Binding = 'binding';
    case Instance = 'instance';
    case Autowired = 'autowired';
    case Unresolvable = 'unresolvable';

    public function isProvided(): bool
    {
        return $this !== self::Unresolvable;
    }
}
