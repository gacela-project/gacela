# PHPStan Module Boundary Enforcement

This PHPStan extension helps enforce module boundaries in Gacela applications by preventing direct access to internal classes across modules.

## What it does

The rule ensures that:
- ✅ Modules can only communicate through Facades
- ❌ Direct access to Domain classes from other modules is prevented
- ❌ Direct access to Infrastructure classes from other modules is prevented
- ✅ Classes within the same module can access each other freely
- ✅ Test files are exempted from boundary checks

## Installation

Add the PHPStan modules configuration to your `phpstan.neon`:

```neon
includes:
    - vendor/gacela-project/gacela/phpstan-modules.neon
```

## Configuration

The default configuration is:

```neon
parameters:
    gacela:
        moduleBoundary:
            allowedPaths:
                - tests/
                - vendor/
            restrictedPaths:
                - Domain
                - Infrastructure
```

### Customization

You can override these settings in your own `phpstan.neon`:

```neon
parameters:
    gacela:
        moduleBoundary:
            allowedPaths:
                - tests/
                - vendor/
                - scripts/
            restrictedPaths:
                - Domain
                - Infrastructure
                - Application
```

## Examples

### ✅ Allowed: Facade access across modules

```php
namespace App\Checkout;

use App\Cart\CartFacade;

class CheckoutFactory extends AbstractFactory
{
    public function createService(): CheckoutService
    {
        // OK: Using facade from another module
        return new CheckoutService(new CartFacade());
    }
}
```

### ❌ Forbidden: Direct domain access across modules

```php
namespace App\Checkout;

use App\Cart\Domain\CartService;

class CheckoutFactory extends AbstractFactory
{
    public function createService(): CheckoutService
    {
        // ERROR: Cannot directly access Domain class from another module
        return new CheckoutService(new CartService());
    }
}
```

**Error message:**
```
Module boundary violation: Factory from module "Checkout" cannot directly
access Domain class from module "Cart". Use the module's Facade instead.
```

### ❌ Forbidden: Direct infrastructure access across modules

```php
namespace App\Checkout;

use App\Cart\Infrastructure\CartRepository;

class CheckoutFactory extends AbstractFactory
{
    public function createService(): CheckoutService
    {
        // ERROR: Cannot directly access Infrastructure class from another module
        return new CheckoutService(new CartRepository());
    }
}
```

## Module Structure

The rule detects modules by analyzing the namespace structure:

```
App\
├── Cart\                    <- Module: Cart
│   ├── CartFacade           <- Public API
│   ├── CartFactory
│   ├── Domain\              <- Protected
│   │   └── CartService
│   └── Infrastructure\      <- Protected
│       └── CartRepository
└── Checkout\                <- Module: Checkout
    ├── CheckoutFacade       <- Public API
    ├── CheckoutFactory
    └── Domain\              <- Protected
        └── CheckoutService
```

## Benefits

1. **Maintainability**: Clear module boundaries make refactoring safer
2. **Testability**: Modules can be tested in isolation
3. **Scalability**: Teams can work on different modules independently
4. **Architecture enforcement**: Prevents architectural drift over time

## Running the analysis

```bash
# Run PHPStan with module boundary checks
vendor/bin/phpstan analyse

# Run only on specific paths
vendor/bin/phpstan analyse src/
```
