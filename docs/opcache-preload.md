# Opcache Preload

Gacela provides an opcache preload script to improve performance in production environments by loading framework files into shared memory at startup.

## Benefits

- **20-30% performance improvement**
- Reduced memory usage per request
- Faster bootstrap time
- Lower CPU usage

## Requirements

- PHP 8.1+
- Opcache enabled
- Production environment

## Quick Start

### 1. Configure PHP

Add to `/etc/php/8.3/fpm/php.ini` or pool config:

```ini
opcache.enable=1
opcache.preload=/path/to/project/vendor/gacela-project/gacela/resources/gacela-preload.php
opcache.preload_user=www-data
```

### 2. Restart PHP-FPM

```bash
sudo systemctl restart php8.3-fpm
```

### 3. Verify

Check logs for: `Gacela Opcache Preload: 34 files preloaded successfully`

## What Gets Preloaded

The script preloads 34 core Gacela framework files:
- Bootstrap & Configuration
- Class Resolvers (Facade, Factory, Config, Provider)
- Cache implementations
- Base classes (AbstractFacade, AbstractFactory, etc.)
- Container & Events

## Advanced: Preload Your Application

Create `config/app-preload.php`:

```php
<?php
$root = dirname(__DIR__);

// Preload your high-traffic modules
opcache_compile_file($root . '/src/User/UserFacade.php');
opcache_compile_file($root . '/src/Product/ProductFacade.php');
```

Then configure:

```ini
# PHP-FPM pool config
env[GACELA_PRELOAD_USER_FILES] = /path/to/project/config/app-preload.php
```

## Common Issues

### Files Not Preloading?

```bash
# Check PHP version
php -v  # Must be 8.1+

# Verify opcache is enabled
php -i | grep opcache.enable

# Check permissions
ls -la vendor/gacela-project/gacela/resources/gacela-preload.php
```

### Permission Denied?

```bash
# Fix permissions
chmod 644 vendor/gacela-project/gacela/resources/gacela-preload.php

# Verify preload user matches PHP-FPM user
ps aux | grep php-fpm
```

## Deployment

Always restart PHP-FPM after deploying code changes:

```bash
git pull
composer install --no-dev --optimize-autoloader
sudo systemctl restart php8.3-fpm
```

## Should I Use This?

**✅ Yes, if you:**
- Run high-traffic production applications
- Need maximum performance
- Have PHP 8.1+

**❌ No, if you:**
- Are developing locally (must restart after each change)
- Have low traffic
- Deploy very frequently

## Docker Example

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

## Learn More

- [PHP Opcache Documentation](https://www.php.net/manual/en/book.opcache.php)
- [Gacela Documentation](https://gacela-project.com)
