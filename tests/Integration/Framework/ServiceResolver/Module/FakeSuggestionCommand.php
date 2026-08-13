<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\Module;

use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * Owned solely by the assertion that the notice names the class it resolved.
 * The notice is emitted once per caller-and-method per process, so a fixture
 * shared with another test would make that assertion depend on suite order --
 * see {@see FakeUseStatementCommand} for the same reasoning.
 *
 * @method FakeFacade getFacade()
 */
final class FakeSuggestionCommand
{
    use ServiceResolverAwareTrait;
}
