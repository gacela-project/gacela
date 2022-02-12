<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\FlexibleService\FlexibleServiceResolver;

trait FlexibleServiceAwareTrait
{
    /** @var array<string,AbstractFlexibleService> */
    private array $flexibleServices = [];

    /**
     * @return mixed
     */
    public function __call(string $name, array $arguments = [])
    {
        if (!isset($this->flexibleServices[$name])) {
            $this->flexibleServices[$name] = (new FlexibleServiceResolver())->resolve($this);
        }

        return $this->flexibleServices[$name];
    }
}
