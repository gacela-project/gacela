<?php

declare(strict_types=1);

require \dirname(__DIR__) . '/vendor/autoload.php';

// Pre-load vendor's PhpParser\ParserAbstract before any PHPStan phar
// interaction. The phar bundles unprefixed PhpParser\* classes in its
// classmap; when PHPStan's PharAutoloader registers the phar's Composer
// autoloader (prepended), it would shadow vendor's php-parser and cause
// a fatal LSP error if the versions differ.
class_exists(PhpParser\ParserAbstract::class);
