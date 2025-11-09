# Static Analysis

Gacela provides configuration files for PHPStan and Psalm to suppress false positives from dynamic resolution via `#[ServiceMap]` attributes.

## PHPStan

Include in your `phpstan.neon`:

```neon
includes:
    - vendor/gacela-project/gacela/phpstan-gacela.neon
```

This suppresses all Gacela-related errors: magic methods, config methods, and type mismatches.

## Psalm

### Setup

Add to your `psalm.xml`:

```xml
<?xml version="1.0"?>
<psalm
    xmlns:xi="http://www.w3.org/2001/XInclude"
    xmlns="https://getpsalm.org/schema/config"
>
    <xi:include href="vendor/gacela-project/gacela/psalm-gacela.xml"/>

    <issueHandlers>
        <!-- Add InvalidArgument suppression (see below) -->
    </issueHandlers>
</psalm>
```

### What's Suppressed

The included `psalm-gacela.xml` suppresses:
- `UndefinedMagicMethod` for `getFacade()`, `getFactory()`, `getConfig()`
- `UndefinedMethod` for all `AbstractConfig` methods

### InvalidArgument Suppression

You must add `InvalidArgument` suppression to your `psalm.xml`:

```xml
<issueHandlers>
    <xi:include href="vendor/gacela-project/gacela/psalm-gacela.xml"/>

    <InvalidArgument>
        <errorLevel type="suppress">
            <directory name="src" />
        </errorLevel>
    </InvalidArgument>
</issueHandlers>
```

Or suppress inline for specific cases:

```php
/** @psalm-suppress InvalidArgument */
return new YourService($this->getConfig());
```


## Troubleshooting

**PHPStan errors**: Verify the include path is `vendor/gacela-project/gacela/phpstan-gacela.neon`

**Psalm errors**:
1. Check `xmlns:xi="http://www.w3.org/2001/XInclude"` is declared
2. Clear cache: `vendor/bin/psalm --clear-cache`

## Learn More

- [PHPStan Ignoring Errors](https://phpstan.org/user-guide/ignoring-errors)
- [Psalm Configuration](https://psalm.dev/docs/running_psalm/configuration/)
- [Gacela ServiceMap](https://gacela-project.com/docs/service-map/)
