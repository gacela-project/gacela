# Event-Driven Module Communication

Event-driven communication enables loosely coupled module interactions through the EventBus, reducing direct dependencies between modules.

## Overview

The EventBus provides a publish-subscribe pattern for modules to communicate without tight coupling. Instead of directly calling another module's facade, modules can emit events that other modules listen to.

## Benefits

- **Loose coupling**: Modules don't need to know about each other
- **Extensibility**: New modules can listen to existing events without changing the publisher
- **Scalability**: Multiple modules can respond to the same event
- **Testability**: Events can be tested independently from their handlers

## Basic Usage

### 1. Create an Event

Extend `ModuleEvent` to create a domain event:

```php
namespace App\Order\Event;

use Gacela\Framework\Event\ModuleEvent;

final class OrderPlacedEvent extends ModuleEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly float $totalAmount,
    ) {
        parent::__construct();
    }
}
```

### 2. Dispatch Events

Use `EventBus::dispatch()` to emit events from your module:

```php
namespace App\Order;

use App\Order\Event\OrderPlacedEvent;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Event\EventBus;

final class OrderFacade extends AbstractFacade
{
    public function placeOrder(string $customerId, array $items): string
    {
        $orderId = $this->getFactory()
            ->createOrderService()
            ->createOrder($customerId, $items);

        // Dispatch event for other modules
        EventBus::dispatch(new OrderPlacedEvent(
            orderId: $orderId,
            customerId: $customerId,
            totalAmount: $this->calculateTotal($items),
        ));

        return $orderId;
    }
}
```

### 3. Listen to Events

Register listeners in your `gacela.php` file:

```php
use App\Notification\Event\OrderPlacedListener;
use App\Order\Event\OrderPlacedEvent;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\EventBus;

return static function (GacelaConfig $config): void {
    // Register during bootstrap
    $config->registerSpecificListener(
        OrderPlacedEvent::class,
        new OrderPlacedListener(),
    );

    // Or register dynamically at runtime
    EventBus::listen(
        OrderPlacedEvent::class,
        static function (OrderPlacedEvent $event): void {
            // Handle the event
            sendNotification($event->customerId, $event->orderId);
        },
    );
};
```

## Practical Example

### Scenario: Order Processing System

When an order is placed, multiple modules need to react:
- **Inventory** module needs to update stock
- **Notification** module sends confirmation email
- **Analytics** module tracks the sale

#### Without Events (Tight Coupling)

```php
final class OrderFacade extends AbstractFacade
{
    public function placeOrder(string $customerId, array $items): string
    {
        $orderId = $this->createOrder($customerId, $items);

        // Tight coupling to other modules
        $inventoryFacade = new InventoryFacade();
        $inventoryFacade->reduceStock($items);

        $notificationFacade = new NotificationFacade();
        $notificationFacade->sendOrderConfirmation($customerId, $orderId);

        $analyticsFacade = new AnalyticsFacade();
        $analyticsFacade->trackSale($orderId, $items);

        return $orderId;
    }
}
```

#### With Events (Loose Coupling)

```php
// Order Module
final class OrderFacade extends AbstractFacade
{
    public function placeOrder(string $customerId, array $items): string
    {
        $orderId = $this->createOrder($customerId, $items);

        // Just emit the event
        EventBus::dispatch(new OrderPlacedEvent($orderId, $customerId, $items));

        return $orderId;
    }
}

// Inventory Module listens and reacts
EventBus::listen(OrderPlacedEvent::class, static function (OrderPlacedEvent $event): void {
    (new InventoryFacade())->reduceStock($event->items);
});

// Notification Module listens and reacts
EventBus::listen(OrderPlacedEvent::class, static function (OrderPlacedEvent $event): void {
    (new NotificationFacade())->sendOrderConfirmation($event->customerId, $event->orderId);
});

// Analytics Module listens and reacts
EventBus::listen(OrderPlacedEvent::class, static function (OrderPlacedEvent $event): void {
    (new AnalyticsFacade())->trackSale($event->orderId, $event->items);
});
```

## ModuleEvent Base Class

The `ModuleEvent` base class provides:

- **Timestamp**: Automatic tracking of when the event occurred
- **Event name**: Defaults to the class name
- **String representation**: Useful for logging and debugging

```php
$event = new OrderPlacedEvent('123', 'customer-456', 99.99);

echo $event->getName();        // "App\Order\Event\OrderPlacedEvent"
echo $event->getTimestamp();   // 1612345678.123
echo $event->toString();       // "App\Order\Event\OrderPlacedEvent (timestamp: 2021-02-03 12:34:38)"
```

## Event Naming Conventions

- **Past tense**: Events describe something that already happened
  - ✅ `OrderPlacedEvent`
  - ❌ `PlaceOrderEvent`

- **Specific**: Be clear about what happened
  - ✅ `OrderCancelledEvent`
  - ❌ `OrderEvent`

- **Domain-focused**: Use business language
  - ✅ `PaymentProcessedEvent`
  - ❌ `DataUpdatedEvent`

## Testing

### Testing Event Dispatch

```php
final class OrderFacadeTest extends TestCase
{
    public function test_dispatches_order_placed_event(): void
    {
        $dispatched = false;

        EventBus::listen(OrderPlacedEvent::class, static function () use (&$dispatched): void {
            $dispatched = true;
        });

        $facade = new OrderFacade();
        $facade->placeOrder('customer-123', []);

        self::assertTrue($dispatched, 'OrderPlacedEvent should have been dispatched');
    }
}
```

### Testing Event Listeners

```php
final class InventoryListenerTest extends TestCase
{
    public function test_reduces_stock_when_order_placed(): void
    {
        $event = new OrderPlacedEvent('order-123', 'customer-456', [
            ['sku' => 'WIDGET-1', 'quantity' => 2],
        ]);

        $listener = new InventoryListener();
        $listener($event);

        $stock = (new InventoryFacade())->getStock('WIDGET-1');
        self::assertEquals(8, $stock); // Assuming started with 10
    }
}
```

## When to Use Events vs. Direct Calls

### Use Events When:
- ✅ Multiple modules need to react to the same action
- ✅ The response is not critical to the current operation
- ✅ You want to decouple modules
- ✅ The reaction can happen asynchronously

### Use Direct Facade Calls When:
- ✅ You need the result immediately
- ✅ The operation is a required dependency
- ✅ Only one module cares about the operation
- ✅ The relationship is inherently coupled (e.g., validation)

## Advanced: Generic Listeners

Listen to all events for cross-cutting concerns:

```php
// In gacela.php
$config->registerGenericListener(static function (object $event): void {
    // Log all events
    if ($event instanceof ModuleEvent) {
        error_log("Event: " . $event->toString());
    }
});
```

## Performance Considerations

- Events are synchronous by default (listeners execute immediately)
- Keep listeners lightweight
- Avoid circular event chains (Event A → Listener B → Event C → Listener A)
- For heavy operations, consider queuing the work

## API Reference

### EventBus

```php
// Dispatch an event
EventBus::dispatch(object $event): void

// Register a listener
EventBus::listen(string $eventClass, callable $listener): void
```

### ModuleEvent

```php
// Get event name
$event->getName(): string

// Get creation timestamp
$event->getTimestamp(): float

// Get string representation
$event->toString(): string
```
