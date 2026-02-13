# Module Health Checks

Health checks enable modules to report their operational status, making it easy to monitor system health and detect issues early.

## Overview

The health check system provides:
- **Module-level monitoring**: Each module can report its health independently
- **Aggregated reporting**: Get an overall system health view
- **Flexible status levels**: Healthy, Degraded, or Unhealthy
- **Contextual information**: Include metadata with each health check

## Benefits

- **Early problem detection**: Identify issues before they cause failures
- **Monitoring integration**: Easily integrate with monitoring tools
- **Dependency validation**: Check external dependencies (database, APIs, etc.)
- **Deployment confidence**: Verify system health after deployments

## Quick Start

### 1. Implement Health Check

Implement `ModuleHealthCheckInterface` in your module:

```php
namespace App\Database;

use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use PDO;

final class DatabaseHealthCheck implements ModuleHealthCheckInterface
{
    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    public function checkHealth(): HealthStatus
    {
        try {
            $stmt = $this->connection->query('SELECT 1');

            if ($stmt === false) {
                return HealthStatus::unhealthy('Database query failed');
            }

            return HealthStatus::healthy('Database connection is operational');
        } catch (\Throwable $e) {
            return HealthStatus::unhealthy(
                'Database connection failed',
                ['error' => $e->getMessage()],
            );
        }
    }

    public function getModuleName(): string
    {
        return 'Database';
    }
}
```

### 2. Register Health Checks

Register your health checks with the HealthChecker:

```php
use App\Database\DatabaseHealthCheck;
use Gacela\Framework\Health\HealthChecker;

$healthChecker = new HealthChecker([
    new DatabaseHealthCheck($pdo),
    new CacheHealthCheck($redis),
    new ApiHealthCheck($httpClient),
]);
```

### 3. Run Health Checks

Execute all health checks and get a report:

```php
$report = $healthChecker->checkAll();

if ($report->isHealthy()) {
    echo "All systems operational\n";
} else {
    echo "System health: {$report->getOverallLevel()->value}\n";

    foreach ($report->getResults() as $moduleName => $status) {
        echo "{$moduleName}: {$status->level->value} - {$status->message}\n";
    }
}
```

## Health Status Levels

### Healthy

Everything is working as expected:

```php
HealthStatus::healthy('All services operational');
```

### Degraded

Working but with reduced performance or non-critical issues:

```php
HealthStatus::degraded(
    'High latency detected',
    ['avg_response_time' => '500ms', 'threshold' => '100ms'],
);
```

### Unhealthy

Not working properly or critical failure:

```php
HealthStatus::unhealthy(
    'Service unavailable',
    ['error_code' => 503, 'retries' => 3],
);
```

## Practical Examples

### Database Connection Check

```php
final class DatabaseHealthCheck implements ModuleHealthCheckInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function checkHealth(): HealthStatus
    {
        try {
            $start = microtime(true);
            $this->pdo->query('SELECT 1');
            $latency = (microtime(true) - $start) * 1000;

            if ($latency > 100) {
                return HealthStatus::degraded(
                    'Database latency is high',
                    ['latency_ms' => $latency],
                );
            }

            return HealthStatus::healthy('Database responsive', [
                'latency_ms' => $latency,
            ]);
        } catch (\PDOException $e) {
            return HealthStatus::unhealthy(
                'Database connection failed',
                ['error' => $e->getMessage()],
            );
        }
    }

    public function getModuleName(): string
    {
        return 'Database';
    }
}
```

### External API Check

```php
final class PaymentApiHealthCheck implements ModuleHealthCheckInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $apiUrl,
    ) {
    }

    public function checkHealth(): HealthStatus
    {
        try {
            $response = $this->client->get($this->apiUrl . '/health');

            if ($response->getStatusCode() === 200) {
                return HealthStatus::healthy('Payment API is reachable');
            }

            return HealthStatus::degraded(
                'Payment API returned non-200 status',
                ['status_code' => $response->getStatusCode()],
            );
        } catch (\Throwable $e) {
            return HealthStatus::unhealthy(
                'Cannot reach Payment API',
                ['error' => $e->getMessage()],
            );
        }
    }

    public function getModuleName(): string
    {
        return 'PaymentAPI';
    }
}
```

### File System Check

```php
final class StorageHealthCheck implements ModuleHealthCheckInterface
{
    public function __construct(
        private readonly string $storagePath,
        private readonly int $minFreeSpaceGB = 5,
    ) {
    }

    public function checkHealth(): HealthStatus
    {
        if (!is_dir($this->storagePath)) {
            return HealthStatus::unhealthy(
                'Storage directory does not exist',
                ['path' => $this->storagePath],
            );
        }

        if (!is_writable($this->storagePath)) {
            return HealthStatus::unhealthy(
                'Storage directory is not writable',
                ['path' => $this->storagePath],
            );
        }

        $freeSpaceGB = disk_free_space($this->storagePath) / 1024 / 1024 / 1024;

        if ($freeSpaceGB < $this->minFreeSpaceGB) {
            return HealthStatus::degraded(
                'Low disk space',
                [
                    'free_space_gb' => round($freeSpaceGB, 2),
                    'threshold_gb' => $this->minFreeSpaceGB,
                ],
            );
        }

        return HealthStatus::healthy('Storage available', [
            'free_space_gb' => round($freeSpaceGB, 2),
        ]);
    }

    public function getModuleName(): string
    {
        return 'Storage';
    }
}
```

### Cache Connection Check

```php
final class CacheHealthCheck implements ModuleHealthCheckInterface
{
    public function __construct(
        private readonly RedisInterface $redis,
    ) {
    }

    public function checkHealth(): HealthStatus
    {
        try {
            $key = 'health_check_' . time();
            $this->redis->set($key, '1', 1);
            $value = $this->redis->get($key);

            if ($value !== '1') {
                return HealthStatus::degraded(
                    'Cache read/write mismatch',
                );
            }

            return HealthStatus::healthy('Cache is operational');
        } catch (\Throwable $e) {
            return HealthStatus::unhealthy(
                'Cache connection failed',
                ['error' => $e->getMessage()],
            );
        }
    }

    public function getModuleName(): string
    {
        return 'Cache';
    }
}
```

## Health Check Report

The `HealthCheckReport` aggregates all module statuses:

### Check Overall Health

```php
$report = $healthChecker->checkAll();

if ($report->isHealthy()) {
    // All modules are healthy
}

if ($report->hasUnhealthyModules()) {
    // At least one module is unhealthy
}

// Get the worst status
$overallLevel = $report->getOverallLevel(); // HEALTHY, DEGRADED, or UNHEALTHY
```

### Filter by Status Level

```php
// Get all unhealthy modules
$unhealthy = $report->getResultsByLevel(HealthLevel::UNHEALTHY);

foreach ($unhealthy as $moduleName => $status) {
    error_log("Unhealthy module: {$moduleName} - {$status->message}");
}
```

### Export for Monitoring

```php
// Convert to array for JSON API or monitoring systems
$data = $report->toArray();

/*
[
    'overall' => 'degraded',
    'modules' => [
        'Database' => [
            'level' => 'healthy',
            'message' => 'Database responsive',
            'metadata' => ['latency_ms' => 5.2],
        ],
        'PaymentAPI' => [
            'level' => 'degraded',
            'message' => 'High latency detected',
            'metadata' => ['avg_response_time' => '500ms'],
        ],
    ],
]
*/

header('Content-Type: application/json');
echo json_encode($data);
```

## HTTP Health Check Endpoint

Create a health check endpoint for monitoring tools:

```php
// In your HTTP controller
public function healthCheck(): Response
{
    $healthChecker = new HealthChecker([
        new DatabaseHealthCheck($this->pdo),
        new CacheHealthCheck($this->redis),
        new ApiHealthCheck($this->httpClient),
    ]);

    $report = $healthChecker->checkAll();

    $statusCode = match ($report->getOverallLevel()) {
        HealthLevel::HEALTHY => 200,
        HealthLevel::DEGRADED => 200, // Or 206 for partial content
        HealthLevel::UNHEALTHY => 503,
    };

    return new JsonResponse(
        $report->toArray(),
        $statusCode,
    );
}
```

## Best Practices

### 1. Keep Checks Lightweight

Health checks should be fast (< 1 second):

```php
// ❌ Bad: Slow check
public function checkHealth(): HealthStatus
{
    $this->runFullDatabaseMigration(); // Too slow!
    return HealthStatus::healthy();
}

// ✅ Good: Fast check
public function checkHealth(): HealthStatus
{
    $this->pdo->query('SELECT 1'); // Quick ping
    return HealthStatus::healthy();
}
```

### 2. Use Appropriate Status Levels

```php
// ✅ Healthy: Everything is optimal
HealthStatus::healthy('API responding in 50ms');

// ✅ Degraded: Working but suboptimal
HealthStatus::degraded('API slow (500ms), using fallback cache');

// ✅ Unhealthy: Critical failure
HealthStatus::unhealthy('API unreachable after 3 retries');
```

### 3. Include Useful Metadata

```php
// ❌ Bad: No context
HealthStatus::unhealthy('Failed');

// ✅ Good: Actionable information
HealthStatus::unhealthy('Database connection timeout', [
    'host' => 'db.example.com',
    'port' => 5432,
    'timeout_seconds' => 5,
    'last_successful_connection' => '2024-01-15 10:30:00',
]);
```

### 4. Handle Exceptions Gracefully

```php
public function checkHealth(): HealthStatus
{
    try {
        // Perform check
        return HealthStatus::healthy();
    } catch (\Throwable $e) {
        // Don't let exceptions crash the health check system
        return HealthStatus::unhealthy(
            'Health check failed with exception',
            [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ],
        );
    }
}
```

## Monitoring Integration

### Kubernetes Liveness/Readiness Probes

```yaml
livenessProbe:
  httpGet:
    path: /health
    port: 8080
  initialDelaySeconds: 10
  periodSeconds: 30

readinessProbe:
  httpGet:
    path: /health
    port: 8080
  initialDelaySeconds: 5
  periodSeconds: 10
```

### Prometheus Metrics

```php
$report = $healthChecker->checkAll();

// Export as Prometheus metrics
$metrics = [];
foreach ($report->getResults() as $moduleName => $status) {
    $value = match ($status->level) {
        HealthLevel::HEALTHY => 1,
        HealthLevel::DEGRADED => 0.5,
        HealthLevel::UNHEALTHY => 0,
    };

    $metrics[] = "module_health{{module=\"{$moduleName}\"}} {$value}";
}

echo implode("\n", $metrics);
```

## Testing Health Checks

```php
final class DatabaseHealthCheckTest extends TestCase
{
    public function test_returns_healthy_when_database_responds(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())
            ->method('query')
            ->with('SELECT 1')
            ->willReturn($this->createMock(PDOStatement::class));

        $healthCheck = new DatabaseHealthCheck($pdo);
        $status = $healthCheck->checkHealth();

        self::assertTrue($status->isHealthy());
    }

    public function test_returns_unhealthy_when_database_fails(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')
            ->willThrowException(new PDOException('Connection failed'));

        $healthCheck = new DatabaseHealthCheck($pdo);
        $status = $healthCheck->checkHealth();

        self::assertTrue($status->isUnhealthy());
        self::assertStringContainsString('failed', $status->message);
    }
}
```

## API Reference

### ModuleHealthCheckInterface

```php
interface ModuleHealthCheckInterface
{
    public function checkHealth(): HealthStatus;
    public function getModuleName(): string;
}
```

### HealthStatus

```php
// Factory methods
HealthStatus::healthy(string $message = '...', array $metadata = []): self
HealthStatus::degraded(string $message, array $metadata = []): self
HealthStatus::unhealthy(string $message, array $metadata = []): self

// Properties
$status->level: HealthLevel
$status->message: string
$status->metadata: array

// Methods
$status->isHealthy(): bool
$status->isDegraded(): bool
$status->isUnhealthy(): bool
$status->toArray(): array
```

### HealthCheckReport

```php
$report->isHealthy(): bool
$report->hasUnhealthyModules(): bool
$report->getResults(): array<string, HealthStatus>
$report->getResultsByLevel(HealthLevel $level): array
$report->getOverallLevel(): HealthLevel
$report->toArray(): array
```

### HealthChecker

```php
$checker->checkAll(): HealthCheckReport
$checker->count(): int
```
