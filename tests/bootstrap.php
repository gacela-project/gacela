<?php

declare(strict_types=1);

require \dirname(__DIR__) . '/vendor/autoload.php';

// PHPStan's phar bundles ~250 unprefixed PhpParser\* classes in its
// Composer classmap.  When RuleErrorBuilder (or any PHPStan class that
// references _PHPStan_ internals) is loaded, the phar's Composer
// autoloader is registered with spl_autoload_register(..., prepend:true),
// jumping ahead of vendor's autoloader.  If the phar's php-parser version
// differs from vendor's, loading any not-yet-loaded PhpParser class from
// the phar causes a fatal LSP / property-type error.
//
// Fix: read the phar's classmap, then pre-load every PhpParser class it
// exposes via vendor's PSR-4 autoloader (the only one registered at this
// point).  Once loaded, PHP will never re-autoload them from the phar.
$pharPath = \dirname(__DIR__) . '/vendor/phpstan/phpstan/phpstan.phar';

if (file_exists($pharPath) && \extension_loaded('phar')) {
    $pharClassmap = require 'phar://' . $pharPath . '/vendor/composer/autoload_classmap.php';

    foreach ($pharClassmap as $class => $_file) {
        if (str_starts_with($class, 'PhpParser\\')) {
            class_exists($class);
        }
    }

    unset($pharClassmap, $class, $_file);
}

unset($pharPath);
