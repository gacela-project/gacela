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
