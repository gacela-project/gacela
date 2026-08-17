<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Testing\Module;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

/**
 * An event of the application under test, dispatched through the dispatcher the
 * module was given -- which is what the recording assertions have to see as
 * readily as they see the framework's own.
 */
final class GreetedEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $name,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function toString(): string
    {
        return sprintf('Greeted: %s', $this->name);
    }
}
