<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain;

interface InterfaceFromCallable
{
    public function getClassName(): string;
}
