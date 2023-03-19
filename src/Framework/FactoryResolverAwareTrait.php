<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Factory\FactoryResolver;

/**
 * @psalm-suppress MethodSignatureMismatch
 *
 * @method AbstractFactory getFactory()
 * @method static AbstractFactory factory() Alias to `getFactory()`
 */
trait FactoryResolverAwareTrait
{
    private ?AbstractFactory $factory = null;

    /**
     * Syntax sugar to access the factory from static methods.
     */
    public static function __callStatic(string $name = '', array $arguments = [])
    {
        if ($name === 'factory' || $name === 'getFactory') {
            return (new static())->doGetFactory();
        }

        /** @psalm-suppress ParentNotFound */
        return parent::__callStatic($name, $arguments);
    }

    public function __call(string $name = '', array $arguments = [])
    {
        if ($name === 'factory' || $name === 'getFactory') {
            return $this->doGetFactory();
        }

        /** @psalm-suppress ParentNotFound */
        return parent::__call($name, $arguments);
    }

    private function doGetFactory(): AbstractFactory
    {
        if ($this->factory === null) {
            $this->factory = (new FactoryResolver())->resolve($this);
        }

        return $this->factory;
    }
}
