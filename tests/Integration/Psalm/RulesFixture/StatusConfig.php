<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\RulesFixture;

/**
 * Named after a pillar but unable to extend one -- an enum cannot extend a class
 * at all. Reporting it would be advice nobody can take.
 */
enum StatusConfig: string
{
    case Open = 'open';
}
