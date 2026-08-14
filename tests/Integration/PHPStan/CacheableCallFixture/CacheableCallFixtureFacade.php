<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\CacheableCallFixture;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Attribute\Cacheable;
use Gacela\Framework\Attribute\CacheableTrait;

/**
 * All three shapes at once, so the two that must stay silent are asserted
 * against the same run as the one that must be reported.
 *
 * @method CacheableCallFixtureFactory getFactory()
 */
final class CacheableCallFixtureFacade extends AbstractFacade
{
    use CacheableTrait;

    /**
     * Reported: the attribute is metadata, and nothing reads it here.
     */
    #[Cacheable(ttl: 600, key: 'forgotten-{0}')]
    public function forgotTheWrapper(int $id): int
    {
        return $this->getFactory()->compute($id);
    }

    /**
     * Silent: the shape the documentation opens with.
     */
    #[Cacheable(ttl: 600, key: 'wrapped-{0}')]
    public function wrapped(int $id): int
    {
        return $this->cached(fn (): int => $this->getFactory()->compute($id));
    }

    /**
     * Silent: the documented "opting out of backtrace" shape, where `cached()`
     * lives in a helper and is handed the method and args explicitly. A rule
     * that judged one method at a time could not see the helper and would
     * report this.
     */
    #[Cacheable(ttl: 600, key: 'delegated-{0}')]
    public function delegated(int $id): int
    {
        return $this->viaHelper('delegated', [$id], $id);
    }

    /**
     * @param list<mixed> $args
     */
    private function viaHelper(string $method, array $args, int $id): int
    {
        return $this->cached(fn (): int => $this->getFactory()->compute($id), $method, $args);
    }
}
