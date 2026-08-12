# Profiling

`Gacela\Framework\Profiler\Profiler` is an in-memory stopwatch for your own code: you mark the operations worth measuring, and it records how long each one took and how much memory the process was using when it finished. It is disabled by default and, while disabled, every call on it is a no-op, so instrumentation can stay in place at zero cost.

## Recording spans

Enable the profiler early in your bootstrap, then wrap the code you want to measure in matching `start()` / `stop()` calls with the same operation and subject:

```php
use Gacela\Framework\Profiler\Profiler;

Profiler::getInstance()->enable();
```

```php
$profiler = Profiler::getInstance();

$profiler->start('db-query', 'users');
$users = $repository->findAll();
$profiler->stop('db-query', 'users');
```

- A span is identified by `operation:subject`. Different subjects under one operation stay separate entries and are aggregated per operation in the stats.
- Nested and recursive spans with the same label are handled correctly: start times are kept as a stack, so a `stop()` closes the span its matching `start()` opened instead of collapsing both into one entry.
- A `stop()` with no matching `start()` is ignored; there is no start time to measure from.
- Durations are measured with `hrtime()` and reported in seconds. Each entry also records `memory_get_usage(true)` at the time it was stopped.
- `disable()` drops any span still in flight, so a later `enable()` cannot pair a fresh `stop()` with a stale start.
- `reset()` clears recorded entries and open spans.

## Reading the results

The profiler lives in process memory, so results are read in the same process that recorded them.

In code, `getEntries()` returns every recorded span, and `getStats()` aggregates them:

```php
$stats = Profiler::getInstance()->getStats();

$stats['total_operations'];  // int
$stats['total_duration'];    // float, seconds
$stats['avg_duration'];      // float, seconds
$stats['peak_memory'];       // int, bytes
$stats['by_operation'];      // per operation: count, total_duration, avg_duration
```

On the command line, `profile:report` renders the same data as a table (`--format=table|json|summary`), sorted by duration, memory, or operation (`--sort`). Because the profiler is per-process, the command shows the spans recorded during its own run: enable the profiler and instrument code inside `gacela.php` or the bootstrap closure, and whatever executes while the command boots is what appears in the report.

## See also

- [CLI commands](cli.md) — `profile:report` among the production commands
- [Events](events.md) — lifecycle events, the other observability surface, better suited to tracing resolution
