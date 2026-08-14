# DTO schema

A Facade returns arrays or hand-written DTOs. An array tells a consumer nothing — the keys live in the producer's head, a mistyped key returns `null`, and static analysis sees `array<string, mixed>`. A hand-written DTO fixes the types and starts a different problem: two modules exchanging one shape write it twice, and the copies drift.

Declare the shape once, generate the class from it.

```php
// gacela.php
use Gacela\Framework\Dto\Schema\DtoType;

$config->declareDtoSchema(App\Checkout\Order::class, [
    'reference'  => DtoType::string()->required(),
    'total'      => DtoType::int()->required()->describe('order total in cents'),
    'couponCode' => DtoType::string(),
    'currency'   => DtoType::string()->default('EUR'),
]);
```

```bash
vendor/bin/gacela dto:generate            # write the classes
vendor/bin/gacela dto:generate --dry-run  # report what would change, write nothing
vendor/bin/gacela dto:generate --check    # the same, and exit non-zero if anything would change
```

`--check` is the CI form: the generated classes are committed, so a declaration edited without regenerating leaves the repository behind what `gacela.php` says. It writes nothing — writing is what it exists to catch — and names each file that is stale.

```php
$order = Order::fromArray(['reference' => 'ord-1187', 'total' => 4990]);

$order->getReference();   // 'ord-1187'
$order->getCouponCode();  // null — optional and never set
$order->getCurrency();    // 'EUR' — a declared default is not missing

$discounted = $order->withCouponCode('SUMMER10');
$order->getCouponCode();  // still null: with*() returns a new instance

Order::fromArray([])->getReference();
// MissingDtoPropertyException: "App\Checkout\Order::$reference" is required but was never set.
```

## Extending a shape you do not own

This is the reason the shapes merge rather than overwrite. A package declares `Order`; a project needs one more field on it; nobody forks a file:

```php
// the package, through extendGacelaConfig()
$config->declareDtoSchema(App\Checkout\Order::class, [
    'reference' => DtoType::string()->required(),
]);

// gacela.php in the project — same class, one more property
$config->declareDtoSchema(App\Checkout\Order::class, [
    'giftMessage' => DtoType::string(),
]);
```

Both declarations produce **one** class carrying both properties.

Adding is safe. **Redefining is refused at bootstrap**, because the package that declared the property first reads the same generated class, so changing it underneath would break code that already compiles. Redeclaring a property *identically* is fine, and so is rewording its `describe()` — the description is prose about the property, not part of its shape.

This is the opposite rule to [the config schema](config-schema.md), deliberately: a config key has one owner, so later-wins is right there. A shape has as many owners as declare it.

## Where the files go

**A shape is declared by the class it generates**, so the file is written where your own `composer.json` `autoload` already looks for that namespace. With `"App\\": "src"`, `App\Checkout\Order` is written to `src/Checkout/Order.php`.

That is what lets the framework register no autoloader for generated code. Two consequences worth having:

- Your existing autoloader already loads the class. Nothing new resolves it at runtime.
- PHPStan, Psalm and your IDE read the files as ordinary PHP. There is no "run the generator before analysis" step in CI, and no analyser extension — **generated code needs none, which is the argument for generating it** rather than inferring it.

A shape under a namespace no `autoload` prefix covers is reported and the command exits non-zero. Nowhere to write it beats writing it where nothing loads it.

## Rules worth knowing

- **The output is derived. Do not edit it.** Regenerating an unchanged declaration produces a byte-identical file, so a repeat run leaves version control quiet.
- **Declaration order never matters.** Shapes and properties are emitted sorted, so two config sources produce the same class whichever loads first.
- **Required and defaulted are exclusive.** A default is what makes an absent value legitimate; requiring it as well is two answers to one question, and is refused.
- **`toArray()` carries every property that has a value**, and omits the ones that do not — so `fromArray($order->toArray())` round-trips to an equal instance. A declared default *is* a value: `currency` appears as `'EUR'` in the array even though nobody set it, while `couponCode` — optional and undefaulted — is absent. Worth knowing where the array becomes JSON, because the consumer cannot tell a `currency` somebody chose from one nobody did.
- **Nothing is generated while booting.** The declaration is only read by `dto:generate`.

## Not here yet

Nested shapes and collections — `DtoType::shape(...)` and `DtoType::listOf(...)` — are not supported. An order without its lines is half a shape, so that is the first thing to add. Today a nested structure is an `array` property.

Value validation beyond types (formats, ranges, business rules) is out of scope: this declares what a shape *is*, not what a valid instance of it means.
