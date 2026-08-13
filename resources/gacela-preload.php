<?php

/**
 * Gacela Opcache Preload Script
 *
 * Loads the Gacela framework into shared memory at PHP startup, so its classes
 * are not read, compiled and linked again on every request.
 *
 * Configuration in php.ini or php-fpm config:
 *   opcache.preload=/path/to/your/project/vendor/gacela-project/gacela/resources/gacela-preload.php
 *   opcache.preload_user=www-data
 *
 * Preloaded classes are snapshotted at startup, so restart PHP-FPM after every
 * deploy.
 *
 * Requirements:
 *   - PHP 8.3 or higher
 *   - Opcache enabled (opcache.preload is not supported on Windows)
 *
 * Which classes get loaded, and why they are discovered rather than listed, is
 * documented on {@see Gacela\Framework\Preload\Preloader}.
 */

declare(strict_types=1);

use Gacela\Framework\Preload\Preloader;

if (PHP_VERSION_ID < 80300) {
    throw new RuntimeException('Opcache preloading requires PHP 8.3 or higher');
}

$gacelaRoot = \dirname(__DIR__);

// The one explicit require: it registers the autoloader every other class here
// is then found through. Composer's autoloader is deliberately not used -- see
// the Preloader docblock.
require_once $gacelaRoot . '/src/Framework/Preload/Preloader.php';

$result = Preloader::run($gacelaRoot);

// Optional: preload the application's own classes too.
$userPreloadFile = getenv('GACELA_PRELOAD_USER_FILES');
if (\is_string($userPreloadFile) && $userPreloadFile !== '' && file_exists($userPreloadFile)) {
    try {
        require_once $userPreloadFile;
    } catch (Throwable $exception) {
        error_log('Gacela Opcache Preload: user file failed: ' . $exception->getMessage());
    }
}

// STDERR does not exist during preload, so this is the only way to be heard.
error_log($result->summary());
