<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\TagAttribute\Export;

use Gacela\Framework\Attribute\Tag;

#[Tag('exporters')]
final class Avro implements Exporter
{
    public function name(): string
    {
        return 'avro';
    }
}
