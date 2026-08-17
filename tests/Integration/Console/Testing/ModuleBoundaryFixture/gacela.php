<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;

/**
 * A three-module application with a boundary problem in it on purpose.
 *
 * Alpha and Beta import each other, so the graph has one cycle; Gamma is a leaf
 * nothing reaches back into. That is the smallest shape in which every finding
 * `ModuleAssertions` can report has something real to report about.
 */
return static function (GacelaConfig $config): void {
    $config->setProjectNamespaces(['GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture']);
    $config->setAppModulePaths(['Alpha', 'Beta', 'Gamma']);
};
