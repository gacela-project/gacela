<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;

/**
 * Configuration for running Gacela's own console against this repository.
 *
 * Without it, `vendor/bin/gacela doctor` (and `list:modules`, `debug:modules`,
 * `cache:warm`) walk the whole repository and pick up `tests/`, where the
 * fixtures are deliberately *separate applications*: several declare their own
 * `gacela.php` with custom pillar suffixes, so scanned under this config they
 * look like misnamed modules. Doctor reported two such false errors on a clean
 * checkout.
 *
 * `src` is where this package's own modules live, so that is what the console
 * should see. Nothing else reads this file -- the test suite bootstraps each
 * fixture from its own directory -- so it only affects the CLI.
 */
return static function (GacelaConfig $config): void {
    $config->setAppModulePaths(['src']);
};
