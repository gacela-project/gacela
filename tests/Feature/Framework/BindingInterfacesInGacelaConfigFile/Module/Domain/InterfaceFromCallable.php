<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain;

interface InterfaceFromCallable
{
    public function getClassName(): string;
}
