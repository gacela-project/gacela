# Opcache Preload

Gacela ships a preload script that loads its core files into shared memory at PHP startup. In production this typically gives a 20–30% throughput boost and lower per-request memory.

**Requires** PHP 8.1+ with opcache enabled.

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

Verify in the logs: `Gacela Opcache Preload: 34 files preloaded successfully`.

## Preload your own files

Create `config/app-preload.php`:

```php
<?php
$root = dirname(__DIR__);

opcache_compile_file($root . '/src/User/UserFacade.php');
opcache_compile_file($root . '/src/Product/ProductFacade.php');
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

- **Use it** for high-traffic production apps on PHP 8.1+.
- **Skip it** in local development (you'd need to restart after every change) or for very low-traffic sites.

## Troubleshooting

| Symptom                 | Check                                                                  |
|-------------------------|------------------------------------------------------------------------|
| Files not preloading    | `php -v` ≥ 8.1, `php -i \| grep opcache.enable`, preload file readable |
| Permission denied       | `opcache.preload_user` must match the PHP-FPM user (`ps aux \| grep php-fpm`) |

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
