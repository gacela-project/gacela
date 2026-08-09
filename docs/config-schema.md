# Config schema

`validate:config` checks the *wiring* — bindings, dependency cycles. Nothing
checked the configuration itself, so a missing or misspelled key surfaced as a
runtime failure in whichever environment lacked it: usually production, usually
far from the file that should have carried it.

Every call site already knows what it expects — `getInt('retries')` says so — but
that expectation was written nowhere a command could read before anything ran.

## Declaring one

```php
// gacela.php
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Schema\ConfigType;

return static function (GacelaConfig $config): void {
    $config->declareConfigSchema([
        'db.dsn'   => ConfigType::string()->required(),
        'retries'  => ConfigType::int()->default(3),
        'features' => ConfigType::array()->required()->describe('feature flags, keyed by name'),
    ]);
};
```

Types: `string()`, `int()`, `float()`, `bool()`, `array()`.

- `required()` — the key must be present after every source is merged.
- `default($value)` — used when no source provides the key, never over one that
  does. A defaulted key is therefore never missing, and cannot also be required.
- `describe($text)` — travels into the violation, where "wrong type" alone
  leaves the reader guessing.

A `float` accepts an `int`: `timeout: 5` in a config file is about the value, not
about PHP's literal syntax. Nothing else is coerced — `'true'` is not a `bool`.

Declarations merge per key, so `declareConfigSchema()` can be called more than
once and an [extended config](container-configuration.md) can refine one key
without repeating the rest. The later declaration of a key wins.

## Where it is checked

Nothing is checked while booting. The declaration is read by the commands you
already run:

```bash
vendor/bin/gacela validate:config   # non-zero when a declared key is unsatisfied
vendor/bin/gacela doctor            # the same, as one more check in the deploy gate
vendor/bin/gacela debug:config      # marks every key declared / undeclared / missing
```

`debug:config` is the counterweight in the other direction: a schema can only
report the keys it declares, so the table flags the ones it does *not*, and lists
a declared key nothing provides even though it has no value to show.

## Checking on boot, locally

```php
$config->validateConfigSchemaOnBoot();
```

Moves the report from a command you have to remember to run to the first thing
that boots, and throws a `ConfigException` listing every violation. It is off by
default so bootstrap does no work a project did not ask for — leave it off in
production, where the deploy gate has already answered the question.

Declared **defaults** are applied either way: a key with a default is not
missing, it is provided by the declaration.

## See also

- [CLI commands](cli.md)
- [Container configuration](container-configuration.md)
- [Module health checks](module-health-checks.md)
