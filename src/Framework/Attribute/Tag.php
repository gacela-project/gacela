<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Attribute;

/**
 * Adds the class to `$name`, as `$config->tag(ExampleClass::class, $name)` in
 * `gacela.php` would.
 *
 * ```php
 * #[Tag('exporters')]
 * final class CsvExporter implements Exporter {}
 * ```
 *
 * `Container::tagged()` yields the ids `gacela.php` tagged first, then the
 * attribute members by class name, then what a module's Provider tagged.
 *
 * Found by scanning the application's module paths, so only classes inside
 * `projectNamespaces` join. `cache:warm --attributes` stores the result.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Tag
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
