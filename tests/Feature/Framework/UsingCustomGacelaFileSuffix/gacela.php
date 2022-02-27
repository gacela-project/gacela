<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomGacelaFileSuffix;

use Gacela\Framework\AbstractConfigGacela;

return static fn () => new class () extends AbstractConfigGacela {
    public function overrideSuffix(): array
    {
        return [
            'Factory' => 'FactCustom',
            'Config' => 'ConfCustom',
            'DependencyProvider' => 'DepProvCustom',
        ];
    }
};
