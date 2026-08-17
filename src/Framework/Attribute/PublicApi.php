<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Attribute;

/**
 * Marks a class, interface or enum as part of its module's public API.
 *
 * A module's surface is wider than its Facade -- DTOs, enums, value objects,
 * plugin contracts, events. This is the module saying so at the source, next to
 * the class it is true of, instead of in a project-global ignore list that lives
 * outside the module that owns it and has to be repeated once per analyser.
 *
 * ```php
 * use Gacela\Framework\Attribute\PublicApi;
 *
 * #[PublicApi]
 * final class InvoiceRecord
 * {
 *     // ...
 * }
 * ```
 *
 * **What it means:** another module may hold this type, name it and call methods
 * on it without going through the owning module's Facade. The cross-module rules
 * ({@see \Gacela\StaticAnalysis\Rules\CrossModuleViaFacadeAnalyser} and
 * {@see \Gacela\StaticAnalysis\Rules\CrossModuleMethodCallAnalyser}) stop
 * reporting it.
 *
 * **What it does not mean:** that any module may now depend on the one that owns
 * it. `DeclaredModuleDependencyAnalyser` and `debug:graph --check` read
 * `module-rules.json` and are deliberately not exempted -- a forbidden edge stays
 * forbidden whether the class at the end of it is published or not. Making the
 * class public answers "may this be touched without the Facade", not "may these
 * two modules be coupled at all".
 *
 * A marker, with no arguments: narrowing an export to named consumers is a
 * separate feature, and the rules already take consumer-side allow-lists.
 *
 * `TARGET_CLASS` is the closest PHP has to "a classlike", so it permits traits
 * too. Nothing reads it there. A trait is `use`d into a class rather than
 * instantiated, named statically or called on, so no cross-module rule ever asks
 * about one -- and an export nothing enforces would read as a promise.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class PublicApi
{
}
