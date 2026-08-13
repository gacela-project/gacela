# Module Health Checks

Report each module's operational status and aggregate them into a single system health view.

## Quick start

### 1. Implement `ModuleHealthCheckInterface`

```php
use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;

final class DatabaseHealthCheck implements ModuleHealthCheckInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function checkHealth(): HealthStatus
    {
        try {
            $this->pdo->query('SELECT 1');
            return HealthStatus::healthy('Database operational');
        } catch (\Throwable $e) {
            return HealthStatus::unhealthy('Database unreachable', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getModuleName(): string
    {
        return 'Database';
    }
}
```

### 2. Register and run

```php
use Gacela\Framework\Health\HealthChecker;

$checker = new HealthChecker([
    new DatabaseHealthCheck($pdo),
    new CacheHealthCheck($redis),
]);

$report = $checker->checkAll();
```

## CLI integration (`vendor/bin/gacela doctor`)

Register a check with `GacelaConfig::addHealthCheck()` in your `gacela.php` and it runs automatically as part of `vendor/bin/gacela doctor`, alongside the built-in cache-staleness, suffix-mismatch and filename-mismatch checks.

`doctor` takes an optional namespace argument to restrict module-scoped checks, and `--strict` to exit non-zero on warnings too, which is the form CI wants.

```php
// gacela.php
use Gacela\Framework\Bootstrap\GacelaConfig;

return static function (GacelaConfig $config): void {
    $config->addHealthCheck(DatabaseHealthCheck::class);
    // or an instance:
    $config->addHealthCheck(new DatabaseHealthCheck($pdo));
};
```

`addHealthCheck()` accepts either a `class-string<ModuleHealthCheckInterface>` (resolved through the container) or a ready-made `ModuleHealthCheckInterface` instance:

```
public function addHealthCheck(string|ModuleHealthCheckInterface $check): self
```

Running the command surfaces each registered check as a `module health: <module name>` line:

```
$ vendor/bin/gacela doctor

Gacela Doctor
============================================================

✓ module health: Database
    Database operational

============================================================
✓ All checks passed
```

A `degraded` check reports as a warning; an `unhealthy` check reports as an error and makes `doctor` exit non-zero. Their metadata is printed under the status line, one `key: value` per line, with a value that is not a scalar named by its type. A **healthy** check stays a single line: it takes metadata like the other two, and `doctor` does not print it, because a passing check is not what you are reading the report for.

Several checks may report under the same module name. Gacela combines them into one module result whose level is the worst result, and keeps every individual status under the result's `health_checks` metadata. A later healthy check therefore cannot hide an earlier degraded or unhealthy one.

## Status levels

| Level       | When to use                                  |
|-------------|----------------------------------------------|
| `healthy`   | Everything works as expected                 |
| `degraded`  | Works but slow or using fallbacks            |
| `unhealthy` | Critical failure                             |

```php
HealthStatus::healthy('API responding in 50ms');
HealthStatus::degraded('High latency', ['avg_ms' => 500]);
HealthStatus::unhealthy('Unreachable', ['retries' => 3]);
```

## HTTP endpoint

```php
public function healthCheck(): Response
{
    $report = $this->healthChecker->checkAll();

    $status = match ($report->getOverallLevel()) {
        HealthLevel::HEALTHY, HealthLevel::DEGRADED => 200,
        HealthLevel::UNHEALTHY => 503,
    };

    return new JsonResponse($report->toArray(), $status);
}
```

`$report->toArray()` returns:

```
[
    'overall' => 'degraded',
    'modules' => [
        'Database' => ['level' => 'healthy', 'message' => '...', 'metadata' => [...]],
        'PaymentAPI' => ['level' => 'degraded', 'message' => '...', 'metadata' => [...]],
    ],
]
```

## Report API

```php
$report->isHealthy();                              // bool
$report->hasUnhealthyModules();                    // bool
$report->getOverallLevel();                        // HealthLevel
$report->getResults();                             // array<string, HealthStatus>
$report->getResultsByLevel(HealthLevel::UNHEALTHY);
$report->toArray();
```

## Best practices

- **Be fast** — checks should complete in under a second. Prefer a quick ping (`SELECT 1`) over full queries.
- **Include metadata** — latency, error codes, retry counts help diagnose issues.
- **Let exceptions go** — you do not need a `try`/`catch`. `HealthChecker` turns any `Throwable` out of a check into an `unhealthy` result carrying the exception, file and line as metadata.
- **Pick the right level** — reserve `unhealthy` for real outages; use `degraded` for slow-but-working.

## API reference

### `ModuleHealthCheckInterface`

```php
interface ModuleHealthCheckInterface
{
    public function checkHealth(): HealthStatus;

    public function getModuleName(): string;
}
```

### `HealthStatus`

```
HealthStatus::healthy(string $message = 'Module is healthy', array $metadata = []): self
HealthStatus::degraded(string $message, array $metadata = []): self
HealthStatus::unhealthy(string $message, array $metadata = []): self

$status->level;       // HealthLevel
$status->message;     // string
$status->metadata;    // array
$status->isHealthy(): bool
$status->isDegraded(): bool
$status->isUnhealthy(): bool
$status->toArray(): array
```

### `HealthChecker`

```
$checker->checkAll(): HealthCheckReport
$checker->count(): int
```

### `GacelaConfig`

```
// Register a check to run under `vendor/bin/gacela doctor`
$config->addHealthCheck(string|ModuleHealthCheckInterface $check): self
```
