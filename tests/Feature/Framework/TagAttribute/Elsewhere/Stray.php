<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\TagAttribute\Elsewhere;

use Gacela\Framework\Attribute\Tag;
use GacelaTest\Feature\Framework\TagAttribute\Export\Exporter;

#[Tag('exporters')]
final class Stray implements Exporter
{
    public function name(): string
    {
        return 'stray';
    }
}
