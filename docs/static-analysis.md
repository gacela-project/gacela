# Static Analysis

Gacela provides configuration files for PHPStan and Psalm to suppress false positives from dynamic resolution via `#[ServiceMap]` attributes.

## Why Needed

Static analysis tools can't understand Gacela's runtime resolution of:
- Magic methods: `getFacade()`, `getFactory()`, `getConfig()`
- Config methods on `AbstractConfig` subclasses

## PHPStan

Include in your `phpstan.neon`:

```neon
includes:
    - vendor/gacela-project/gacela/phpstan-gacela.neon
```

This suppresses errors for magic methods, config methods, and type mismatches.

## Psalm

Include using [XInclude](https://www.w3.org/TR/xinclude/) in your `psalm.xml`:

```xml
<?xml version="1.0"?>
<psalm
    xmlns:xi="http://www.w3.org/2001/XInclude"
    xmlns="https://getpsalm.org/schema/config"
>
    <projectFiles>
        <directory name="src"/>
    </projectFiles>

    <xi:include href="vendor/gacela-project/gacela/psalm-gacela.xml"/>

    <issueHandlers>
        <!-- Your other issue handlers -->
    </issueHandlers>
</psalm>
```

**Note**: Add `xmlns:xi="http://www.w3.org/2001/XInclude"` to enable XInclude support.


## Troubleshooting

**PHPStan errors**: Verify the include path is `vendor/gacela-project/gacela/phpstan-gacela.neon`

**Psalm errors**:
1. Check `xmlns:xi="http://www.w3.org/2001/XInclude"` is declared
2. Clear cache: `vendor/bin/psalm --clear-cache`

## Learn More

- [PHPStan Ignoring Errors](https://phpstan.org/user-guide/ignoring-errors)
- [Psalm Configuration](https://psalm.dev/docs/running_psalm/configuration/)
- [Gacela ServiceMap](https://gacela-project.com/docs/service-map/)
