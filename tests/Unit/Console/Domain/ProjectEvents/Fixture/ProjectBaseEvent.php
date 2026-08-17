<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture;

use Gacela\Framework\Event\GacelaEventInterface;

/**
 * A project's own family parent: never dispatched, and a listener target that
 * covers everything below it -- the same role `AbstractGacelaClassResolverEvent`
 * plays for the framework's resolver events.
 */
abstract class ProjectBaseEvent implements GacelaEventInterface
{
}
