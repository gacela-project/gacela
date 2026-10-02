<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\TagAttribute\Export;

final class Central implements Exporter
{
    public function name(): string
    {
        return 'central';
    }
}
