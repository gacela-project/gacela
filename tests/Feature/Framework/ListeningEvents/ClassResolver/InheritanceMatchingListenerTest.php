<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents\ClassResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\ClassResolver\AbstractGacelaClassResolverEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameNotFoundEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * A specific listener matches by inheritance, through a real bootstrap rather
 * than a hand-built dispatcher: the wiring between `GacelaConfig` and the
 * dispatcher is where a listener has gone missing before (#866).
 *
 * Every count is taken *after* `Gacela::bootstrap()` returns. The bootstrap
 * dispatches events of its own, so a listener counted during it can look alive
 * while being dead for everything that follows.
 */
final class InheritanceMatchingListenerTest extends TestCase
{
    /** @var list<string> */
    private array $fromParent = [];

    /** @var list<string> */
    private array $fromInterface = [];

    /** @var list<string> */
    private array $fromUnrelated = [];

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->resetInMemoryCache();

            $config->registerSpecificListener(
                AbstractGacelaClassResolverEvent::class,
                function (AbstractGacelaClassResolverEvent $event): void {
                    $this->fromParent[] = $event->toString();
                },
            );
            $config->registerSpecificListener(
                GacelaEventInterface::class,
                function (GacelaEventInterface $event): void {
                    $this->fromInterface[] = $event->toString();
                },
            );
            $config->registerSpecificListener(
                ClassNameNotFoundEvent::class,
                function (ClassNameNotFoundEvent $event): void {
                    $this->fromUnrelated[] = $event->toString();
                },
            );
        });

        $this->fromParent = [];
        $this->fromInterface = [];
        $this->fromUnrelated = [];
    }

    public function test_a_listener_on_the_abstract_parent_receives_the_concrete_resolver_event(): void
    {
        (new Module\Facade())->doString();

        self::assertNotEmpty($this->fromParent);
        self::assertStringContainsString('classInfo:', $this->fromParent[0]);
    }

    public function test_a_listener_on_the_event_interface_receives_every_event(): void
    {
        (new Module\Facade())->doString();

        self::assertGreaterThan(count($this->fromParent), count($this->fromInterface));
    }

    public function test_a_listener_on_an_unrelated_event_does_not_fire(): void
    {
        (new Module\Facade())->doString();

        self::assertSame([], $this->fromUnrelated);
    }
}
