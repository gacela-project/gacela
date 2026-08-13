<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\CacheWarm\AttributeWarm;

use Gacela\Framework\AbstractFacade;

/**
 * An ordinary facade, typed generically as the docs recommend.
 *
 * The docblock is deliberately nothing but the generic: `DocBlockParser` picks
 * the first line *containing* the method name it was asked about, so a sentence
 * naming that accessor in prose would answer for it and this fixture would stop
 * being able to raise the deprecation it exists to prove is gone.
 *
 * @extends AbstractFacade<AttributeWarmFactory>
 */
final class AttributeWarmTypedFacade extends AbstractFacade
{
}
