# Static Analysis

Gacela ships configs for PHPStan and Psalm that suppress false positives from dynamic resolution via `#[ServiceMap]` attributes (magic `getFacade()`/`getFactory()`/`getConfig()`, `AbstractConfig` methods, related type mismatches).

## PHPStan

Include in your `phpstan.neon`:

```neon
includes:
    - vendor/gacela-project/gacela/phpstan-gacela.neon
```

## Psalm

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
        <InvalidArgument>
            <errorLevel type="suppress">
                <directory name="src" />
            </errorLevel>
        </InvalidArgument>
    </issueHandlers>
</psalm>
```

The `InvalidArgument` suppression is required — Gacela resolves concrete types at runtime that Psalm can't infer statically. Suppress inline if you prefer narrower scope:

```php
/** @psalm-suppress InvalidArgument */
return new YourService($this->getConfig());
```

## Troubleshooting

- **PHPStan can't find the file** — verify the include path resolves relative to your `phpstan.neon`.
- **Psalm ignores the include** — ensure `xmlns:xi="http://www.w3.org/2001/XInclude"` is declared, then `vendor/bin/psalm --clear-cache`.

## See also

- [PHPStan: ignoring errors](https://phpstan.org/user-guide/ignoring-errors)
- [Psalm configuration](https://psalm.dev/docs/running_psalm/configuration/)
- [Gacela ServiceMap](https://gacela-project.com/docs/service-map/)
