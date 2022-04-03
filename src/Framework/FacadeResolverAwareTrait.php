<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Facade\FacadeResolver;
use function get_class;

trait FacadeResolverAwareTrait
{
    private ?AbstractFacade $facade = null;

    protected function getFacade(): AbstractFacade
    {
        if ($this->facade === null) {
            $thisClass = get_class($this);
            /** @var class-string $classLevelUpNamespace */
            $classLevelUpNamespace = substr($thisClass, 0, (int)strrpos($thisClass, '\\'));
            $this->facade = (new FacadeResolver())->resolve($classLevelUpNamespace);
        }

        return $this->facade;
    }
}
