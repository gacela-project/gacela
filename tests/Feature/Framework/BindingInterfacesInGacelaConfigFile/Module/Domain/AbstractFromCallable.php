<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain;

abstract class AbstractFromCallable
{
    abstract public function getClassName(): string;
}
