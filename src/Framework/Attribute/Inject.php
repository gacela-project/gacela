<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Attribute;
use Gacela\Container\Attribute\Inject as ContainerInject;

/**
 * Marks a constructor parameter, property or setter for injection, optionally
 * naming the implementation to inject.
 *
 * Identical in behaviour to the container's own attribute, which it extends —
 * it exists so application code imports `Gacela\Framework\*` like every other
 * attribute-first surface Gacela exposes, instead of reaching into the
 * container package:
 *
 * ```php
 * use Gacela\Framework\Attribute\Inject;
 *
 * public function __construct(
 *     #[Inject] private readonly LoggerInterface $logger,
 *     #[Inject(RedisCache::class)] private readonly CacheInterface $cache,
 * ) {}
 * ```
 *
 * Either import works and both can appear in one codebase; the container reads
 * attributes with `ReflectionAttribute::IS_INSTANCEOF`, so a subclass is
 * honoured wherever the parent is.
 *
 * That flag is the whole reason this could not ship earlier. RFC-0001 planned a
 * `class_alias()` and withdrew it the same day: an exact-FQN read follows
 * neither an alias nor a subclass, and the failure is **silent** — the
 * parameter is simply never injected. Subclassing is the supported path
 * precisely because the container reads for it.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Inject extends ContainerInject
{
}
