<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ProjectEvents\Ordering;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use GacelaTest\Integration\Framework\ProjectEvents\Ordering\Domain\OrderPlacer;

/**
 * @extends AbstractFactory<OrderingConfig>
 */
final class OrderingFactory extends AbstractFactory
{
    public function createOrderPlacer(): OrderPlacer
    {
        return new OrderPlacer($this->getEventDispatcher());
    }

    /**
     * The dispatcher the application is running with, asked for by its
     * interface like any other provided dependency.
     */
    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->getProvidedDependency(EventDispatcherInterface::class);
    }
}
