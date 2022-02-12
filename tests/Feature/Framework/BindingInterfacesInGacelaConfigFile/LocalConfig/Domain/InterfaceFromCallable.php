<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain;

interface InterfaceFromCallable
{
    public function getClassName(): string;
}
