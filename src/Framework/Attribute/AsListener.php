<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Attribute;

/**
 * Registers the method as a listener of `$event`, as
 * `$config->registerSpecificListener($event, ...)` in `gacela.php` would.
 *
 * ```php
 * final class NotificationFacade extends AbstractFacade
 * {
 *     #[AsListener]
 *     public function onInvoiceIssued(InvoiceIssuedEvent $event): void {}
 * }
 * ```
 *
 * Without `$event`, the type of the first parameter is the event. The class is
 * resolved with `Gacela::getRequired()` when the event is dispatched, so a
 * module double replaces it. It matches by inheritance, like a listener in
 * `gacela.php`, and runs after those.
 *
 * For the application's own events: a Gacela event is dispatched during
 * bootstrap, before the members are read, so its listeners stay in `gacela.php`.
 * Found by scanning the application's module paths, so only classes inside
 * `projectNamespaces` are read. `cache:warm --attributes` stores the result.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class AsListener
{
    /**
     * @param class-string|null $event
     */
    public function __construct(
        public readonly ?string $event = null,
    ) {
    }
}
