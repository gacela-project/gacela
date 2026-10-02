# Long-running runtimes

FrankenPHP worker mode, Laravel Octane, RoadRunner and Swoole serve many requests from one PHP process. Gacela keeps state in the process, and most of it should stay there: what the class names resolved to, reflection, the merged configuration. Some of it must not: the services a request built. One call drops the second kind and keeps the first.

## Reset between requests

```php
use Gacela\Framework\Gacela;

Gacela::resetRequestState();
```

The Symfony bundle and the Laravel bridge call it for you: the bundle from Symfony's `kernel.reset` (FrankenPHP worker mode, RoadRunner, Messenger workers), the bridge after each Octane `RequestTerminated`.

Without a bridge, call it after each request from the runtime's hook. With FrankenPHP's worker loop:

```php
Gacela::bootstrap(__DIR__);

$handler = static function (): void {
    // handle the request
};

while (frankenphp_handle_request($handler)) {
    Gacela::resetRequestState();
}
```

## What is dropped, and what is kept

| Dropped after each request | Kept for the process |
|---|---|
| the Factories, and every `singleton()` they built | what each class name resolved to, and reflection |
| each module's container, with what its Provider `set()` | the merged configuration (`Config`) |
| the resolved Facades, Factories, Configs and Providers | the containers `gacela.php` configured |
| what `Gacela::get()` handed out | event listeners, plugin membership, `#[Cacheable]` storage |

The next request does not walk namespaces, read `gacela.php` or merge config again. It only builds again the services it uses, from plans already in memory.

Every static property in Gacela is classified one way or the other. A test resolves real modules, calls the reset, and fails if it leaves one request static set or clears one process static, so a new static has to be placed on one side when it is written.

## What this asks of your code

- **A singleton declared in `gacela.php` lives for the process**, as a shared service does in any container. Do not bind anything that holds request data there. Set it in the module's Provider, which runs again for every request that uses the module.
- **`#[Cacheable]` results outlive the request**, which is the point of the in-memory store in a worker. The cache key includes the arguments, so a result that depends on the user must take the user as an argument.
- **Configuration is per process.** A request cannot change it. A deploy that changes config restarts the workers.
- **An enabled [profiler](profiling.md) grows with every request.** Keep it off in workers, or reset it yourself.

`Gacela::resetCache()` drops everything, the kept column included, and makes the next request pay a cold bootstrap. It is for tests, not for workers.
