<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\SuffixFixture;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;

/**
 * Silent: the shape the rule asks for, and registered under a different one of
 * the rule's four registrations than the reported class above.
 *
 * @extends AbstractFactory<AbstractConfig>
 */
final class ProperSuffixFactory extends AbstractFactory
{
}
