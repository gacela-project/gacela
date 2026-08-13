# Opcache Preload

Gacela ships a preload script that loads the framework **and the packages it runs on** into shared memory at PHP startup. The container is reached on the first resolution, so leaving it out left the largest single cost in bootstrap on disk. In production it removes the per-request cost of compiling those files and lowers per-request memory. The size of the win depends on your request volume and file count — measure it on your own workload rather than trusting a headline number.

**Requires** PHP 8.3+ with opcache enabled.

## Setup

Add to `php.ini` (or your FPM pool config):

```ini
opcache.enable=1
opcache.preload=/path/to/project/vendor/gacela-project/gacela/resources/gacela-preload.php
opcache.preload_user=www-data
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.3-fpm
```

Verify in the logs: `Gacela Opcache Preload: <n> classes linked, 0 skipped`. The count tracks the framework's size; the `0` is the part to check.

Anything Gacela could not link is named in that line, and PHP logs its own `Can't preload unlinked class ...` warning next to it. Both mean the class was dropped from the image and is being loaded per request as usual — a correctness problem for the preload only, not for your application.

## Preload your own files

Create `config/app-preload.php`. Load the classes rather than compiling the files: a compiled class is only kept if everything it extends, implements and uses was preloaded too, and loading it is what pulls those in.

```php
<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

class_exists(App\User\UserFacade::class);
class_exists(App\Product\ProductFacade::class);
```

Wire it via env var in your FPM pool:

```ini
env[GACELA_PRELOAD_USER_FILES] = /path/to/project/config/app-preload.php
```

## Deployment

Preloaded files are snapshotted at startup — restart PHP-FPM after every deploy:

```bash
composer install --no-dev --optimize-autoloader
sudo systemctl restart php8.3-fpm
```

## When to use it

- **Use it** for high-traffic production apps on PHP 8.3+.
- **Skip it** in local development (you'd need to restart after every change) or for very low-traffic sites.

## Troubleshooting

| Symptom                 | Check                                                                  |
|-------------------------|------------------------------------------------------------------------|
| Files not preloading    | `php -v` ≥ 8.3, `php -i \| grep opcache.enable`, preload file readable |
| Permission denied       | `opcache.preload_user` must match the PHP-FPM user (`ps aux \| grep php-fpm`) |
| `Can't preload unlinked class` | A parent, interface or trait was not preloaded. For your own files, load the class instead of compiling the file (above). |
| Preload aborts on `fopen(php://stdout)` or similar | Some package runs I/O in a composer `files` autoload entry, which the preload context forbids. Install with `--no-dev`, or preload your classes without requiring the full autoloader. |
| Nothing happens on Windows | `opcache.preload` is not supported there. |

## Docker

```dockerfile
FROM php:8.3-fpm
RUN docker-php-ext-install opcache
COPY docker/opcache.ini /usr/local/etc/php/conf.d/
```

```ini
# docker/opcache.ini
opcache.enable=1
opcache.preload=/var/www/html/vendor/gacela-project/gacela/resources/gacela-preload.php
opcache.preload_user=www-data
```

## See also

- [PHP Opcache Documentation](https://www.php.net/manual/en/book.opcache.php)
