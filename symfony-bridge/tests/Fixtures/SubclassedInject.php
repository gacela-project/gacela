<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge\Fixtures;

use Attribute;
use Gacela\Container\Attribute\Inject;

/**
 * Stands in for `Gacela\Framework\Attribute\Inject`, which subclasses the
 * container's attribute for application code.
 *
 * Declared here rather than imported so this suite covers the subclass contract
 * without the bridge depending on the framework package, which it does not
 * require. The container reads every attribute with
 * ReflectionAttribute::IS_INSTANCEOF for exactly this reason.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class SubclassedInject extends Inject {}
