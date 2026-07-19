# Static Analysis

Gacela ships configs for PHPStan and Psalm that suppress false positives from dynamic resolution via `#[ServiceMap]` attributes (magic `getFacade()`/`getFactory()`/`getConfig()`, `AbstractConfig` methods, related type mismatches).

## PHPStan

Include in your `phpstan.neon`:

```neon
includes:
    - vendor/gacela-project/gacela/phpstan-gacela.neon
```

### Module boundaries

The bundled `CrossModuleViaFacadeRule` enforces gacela's core architecture rule
statically: module A may only reach module B through B's Facade. It is opt-in —
register it with your project's root namespace:

```neon
services:
    -
        class: Gacela\PHPStan\Rules\CrossModuleViaFacadeRule
        tags: [phpstan.rules.rule]
        arguments:
            rootNamespace: App\Modules
            modulePathSegments: 1     # how many segments under the root identify a module
            sharedNamespaces:         # optional shared kernels, exempt from the check
                - App\Modules\Shared
```

- Any `new`, static call, class-constant or static-property reference from one
  module into another is reported unless the referenced class is a `*Facade`.
- `sharedNamespaces` entries are exempt in both directions: references into
  them are always allowed, and classes inside them are not checked. Matching is
  namespace-boundary aware (`App\Modules\Shared` does not exempt
  `App\Modules\SharedFoo`).

To see the actual module dependency graph of your app, run
`vendor/bin/gacela debug:graph` (formats: `text`, `mermaid`, `graphviz`, `json`).

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
