<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugin;

interface PluginInterface
{
    public function __invoke(): void;
}
