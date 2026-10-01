<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Attribute;

/**
 * Joins the class to the plugin stack of `$contract`, without naming it in
 * `gacela.php`.
 *
 * ```php
 * #[Plugin(TaxCalculatorInterface::class, priority: 10)]
 * final class EuVatCalculator implements TaxCalculatorInterface {}
 * ```
 *
 * The stack itself is still declared centrally, empty if need be:
 * `$config->addPluginStack(TaxCalculatorInterface::class, [])`. Declared
 * members come first, in declaration order; attribute members follow, by
 * `priority` (highest first) and then by class name.
 *
 * Found by scanning the application's module paths, so only classes inside
 * `projectNamespaces` join. `cache:warm --attributes` stores the result.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Plugin
{
    /**
     * @param class-string $contract
     */
    public function __construct(
        public readonly string $contract,
        public readonly int $priority = 0,
    ) {
    }
}
