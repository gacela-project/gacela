<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain;

abstract class AbstractFromCallable
{
    abstract public function getClassName(): string;
}
