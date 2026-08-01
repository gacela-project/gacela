<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Attribute;

use Gacela\Framework\Container\Container;
use GacelaTest\Integration\Framework\Attribute\InjectAlias\InjectsOnMethod;
use GacelaTest\Integration\Framework\Attribute\InjectAlias\InjectsOnParameter;
use GacelaTest\Integration\Framework\Attribute\InjectAlias\InjectsOnProperty;
use GacelaTest\Integration\Framework\Attribute\InjectAlias\InjectsSpecificImplementation;
use GacelaTest\Integration\Framework\Attribute\InjectAlias\RedCollaborator;
use PHPUnit\Framework\TestCase;

/**
 * `Gacela\Framework\Attribute\Inject` re-presents the container's attribute
 * under the namespace every other Gacela attribute lives in.
 *
 * The risk it carries is why these exist at all: the container reads attributes
 * by type, and if it ever went back to an exact-FQN match the subclass would
 * stop being honoured **silently** — no error, the dependency simply never
 * arriving. RFC-0001 withdrew a `class_alias()` for exactly that reason. So
 * each target the attribute declares is asserted separately.
 */
final class InjectAliasTest extends TestCase
{
    public function test_it_is_honoured_on_a_constructor_parameter(): void
    {
        $resolved = (new Container())->make(InjectsOnParameter::class);

        self::assertInstanceOf(RedCollaborator::class, $resolved->collaborator);
    }

    public function test_it_is_honoured_on_a_property(): void
    {
        $resolved = (new Container())->make(InjectsOnProperty::class);

        self::assertInstanceOf(RedCollaborator::class, $resolved->collaborator());
    }

    public function test_it_is_honoured_on_a_setter(): void
    {
        $resolved = (new Container())->make(InjectsOnMethod::class);

        self::assertInstanceOf(RedCollaborator::class, $resolved->collaborator());
    }

    /**
     * The argument form has to survive the subclass too -- it is the reason to
     * reach for `#[Inject]` over plain autowiring.
     */
    public function test_the_implementation_argument_still_selects_the_concrete(): void
    {
        $resolved = (new Container())->make(InjectsSpecificImplementation::class);

        self::assertInstanceOf(RedCollaborator::class, $resolved->collaborator);
    }
}
