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

## CLI integration (`bin/gacela doctor`)

Register a check with `GacelaConfig::addHealthCheck()` in your `gacela.php` and it runs automatically as part of `bin/gacela doctor`, alongside the built-in cache-staleness and suffix-mismatch checks.

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

```php
public function addHealthCheck(string|ModuleHealthCheckInterface $check): self
```

Running the command surfaces each registered check as a `module health: <module name>` line:

```
$ bin/gacela doctor

Gacela Doctor
============================================================

✓ module health: Database
    Database operational

============================================================
✓ All checks passed
```

A `degraded` check reports as a warning; an `unhealthy` check reports as an error and makes `doctor` exit non-zero. Check metadata is printed under the status line.

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

```php
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
- **Catch exceptions** — never let a failing check crash the health endpoint.
- **Pick the right level** — reserve `unhealthy` for real outages; use `degraded` for slow-but-working.

## API reference

### `ModuleHealthCheckInterface`

```php
public function checkHealth(): HealthStatus;
public function getModuleName(): string;
```

### `HealthStatus`

```php
HealthStatus::healthy(string $message = '', array $metadata = []): self
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

```php
$checker->checkAll(): HealthCheckReport
$checker->count(): int
```

### `GacelaConfig`

```php
// Register a check to run under `bin/gacela doctor`
$config->addHealthCheck(string|ModuleHealthCheckInterface $check): self
```
