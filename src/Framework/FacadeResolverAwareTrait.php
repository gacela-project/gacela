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
            $this->facade = (new FacadeResolver())
                ->resolve($this->classLevelUpNamespace());
        }

        return $this->facade;
    }

    /**
     * @return class-string
     */
    private function classLevelUpNamespace(): string
    {
        $thisClass = get_class($this);

        return substr($thisClass, 0, (int)strrpos($thisClass, '\\'));
    }
}
