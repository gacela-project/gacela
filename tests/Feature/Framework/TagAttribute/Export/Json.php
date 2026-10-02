<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\TagAttribute\Export;

use Gacela\Framework\Attribute\Tag;

#[Tag('reports')]
#[Tag('exporters')]
final class Json implements Exporter
{
    public function name(): string
    {
        return 'json';
    }
}
