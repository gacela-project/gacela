<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Attribute;

/**
 * Marks a facade method as cacheable with optional TTL.
 *
 * The facade must `use CacheableTrait` and the method body must delegate to
 * `$this->cached(fn () => ...)`. The trait infers the method name and
 * arguments from the caller's stack frame and reads this attribute via
 * reflection to obtain the TTL and (optional) key template.
 *
 * The `key` parameter accepts `{N}` placeholders referencing the Nth caller
 * argument; e.g. `key: 'user:{0}'` interpolates the first argument.
 * A bare string with no placeholders is args-agnostic and shared across all
 * calls — rarely what you want when the method takes parameters.
 *
 * Example:
 * ```php
 * use Gacela\Framework\Attribute\Cacheable;
 * use Gacela\Framework\Attribute\CacheableTrait;
 *
 * final class MyFacade
 * {
 *     use CacheableTrait;
 *
 *     #[Cacheable(ttl: 3600, key: 'user:{0}')]
 *     public function getUser(int $id): array
 *     {
 *         return $this->cached(fn (): array =>
 *             $this->getFactory()->createRepository()->find($id),
 *         );
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Cacheable
{
    /**
     * @param int $ttl default TTL in seconds; may be overridden per-method via CacheableConfig::setTtlOverrides()
     * @param string|null $key custom key template (supports `{N}` placeholders); null = auto-generated from class::method::args
     */
    public function __construct(
        public readonly int $ttl = 3600,
        public readonly ?string $key = null,
    ) {
    }
}
