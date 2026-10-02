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
 * Without `$event`, the type of the first parameter is the event. The class,
 * which must be concrete, is resolved like `Gacela::getRequired()` when the
 * event is dispatched. It matches by inheritance, like a listener in
 * `gacela.php`, and runs after those.
 *
 * It serves the dispatcher a module gets from
 * `getProvidedDependency(EventDispatcherInterface::class)`. Gacela's own events
 * keep to the listeners in `gacela.php`.
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
