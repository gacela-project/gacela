<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingGacelaConfigFn\LocalConfig\Domain;

interface GreeterGeneratorInterface
{
    public function company(string $name): string;
}
