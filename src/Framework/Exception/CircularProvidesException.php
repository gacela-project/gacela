<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use RuntimeException;

use function array_map;
use function count;
use function implode;

/**
 * A `#[Provides]` method whose body asks the container for the id it provides,
 * directly or around a loop.
 *
 * The container cannot phrase this one. It sees an id being resolved while it
 * is already being resolved, and can say so -- `CircularDependencyException`
 * does, for the autowiring graph. What it cannot say is *which declaration*
 * caused it, because by then the declaration is an anonymous closure. The
 * provider class, the method carrying the attribute and the id it declares are
 * known only where the closure is built, which is `ProvidesScanner`.
 *
 * The messages are heredocs rather than concatenations, the way
 * `Gacela\Container\Exception\CircularDependencyException` writes its own: the
 * advice is the half a reader acts on, and a sentence assembled from operators
 * is a sentence that can lose one without anything noticing.
 *
 * @psalm-type ProvidesFrame = array{id: string, provider: class-string, method: string}
 */
final class CircularProvidesException extends RuntimeException
{
    /**
     * @param non-empty-list<ProvidesFrame> $chain the frames being resolved, outermost first,
     *                                             beginning at the one the resolution came back to
     */
    public static function detected(array $chain): self
    {
        $head = $chain[0];

        if (count($chain) === 1) {
            $provider = $head['provider'];
            $method = $head['method'];
            $id = $head['id'];

            return new self(<<<TXT
                {$provider}::{$method}() is declared #[Provides({$id}::class)] and its body resolves "{$id}" from the container, so providing it starts by providing it.

                A provided method builds the service; it does not ask for the id it declares. If the body meant a concrete class, name that class instead of the binding id.
                TXT);
        }

        $id = $head['id'];
        $loop = self::renderChain($chain);

        return new self(<<<TXT
            Resolving "{$id}" leads back to itself: {$loop}.

            Each of these is provided by a method that resolves the next one, and the last comes back to the first. Break the loop by building one of them without the container, or by moving the shared part behind a third id.
            TXT);
    }

    /**
     * @param non-empty-list<ProvidesFrame> $chain
     */
    private static function renderChain(array $chain): string
    {
        $steps = array_map(
            static fn (array $frame): string => "{$frame['id']} ({$frame['provider']}::{$frame['method']})",
            $chain,
        );

        // Closed, so the loop reads as one: the id it came back to is the id it
        // started from, and a chain that stops at the last step looks like a
        // path rather than a cycle.
        $steps[] = $chain[0]['id'];

        return implode(' -> ', $steps);
    }
}
