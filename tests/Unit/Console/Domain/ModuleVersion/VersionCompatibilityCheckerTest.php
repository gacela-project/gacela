<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ModuleVersion;

use Gacela\Console\Domain\ModuleVersion\TModuleVersion;
use Gacela\Console\Domain\ModuleVersion\VersionCompatibilityChecker;
use PHPUnit\Framework\TestCase;

use function count;

final class VersionCompatibilityCheckerTest extends TestCase
{
    public function test_compatible_versions(): void
    {
        $moduleVersions = [
            'User' => new TModuleVersion(
                moduleName: 'User',
                version: '1.2.0',
                requiredModules: ['Auth' => '^1.0'],
            ),
            'Auth' => new TModuleVersion(
                moduleName: 'Auth',
                version: '1.5.0',
            ),
        ];

        $checker = new VersionCompatibilityChecker($moduleVersions);
        $result = $checker->checkCompatibility();

        self::assertTrue($result->isCompatible);
        self::assertEmpty($result->errors);
        self::assertEmpty($result->warnings);
    }

    public function test_incompatible_caret_version(): void
    {
        $moduleVersions = [
            'User' => new TModuleVersion(
                moduleName: 'User',
                version: '1.2.0',
                requiredModules: ['Auth' => '^2.0'],
            ),
            'Auth' => new TModuleVersion(
                moduleName: 'Auth',
                version: '1.5.0',
            ),
        ];

        $checker = new VersionCompatibilityChecker($moduleVersions);
        $result = $checker->checkCompatibility();

        self::assertFalse($result->isCompatible);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('Module "User" requires "Auth" version ^2.0 but found 1.5.0', $result->errors[0]);
    }

    public function test_missing_required_module(): void
    {
        $moduleVersions = [
            'User' => new TModuleVersion(
                moduleName: 'User',
                version: '1.2.0',
                requiredModules: ['Auth' => '^1.0'],
            ),
        ];

        $checker = new VersionCompatibilityChecker($moduleVersions);
        $result = $checker->checkCompatibility();

        self::assertFalse($result->isCompatible);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('Module "User" requires "Auth" but it is not defined', $result->errors[0]);
    }

    public function test_tilde_version_compatible(): void
    {
        $moduleVersions = [
            'Product' => new TModuleVersion(
                moduleName: 'Product',
                version: '1.0.0',
                requiredModules: ['Catalog' => '~1.2'],
            ),
            'Catalog' => new TModuleVersion(
                moduleName: 'Catalog',
                version: '1.2.5',
            ),
        ];

        $checker = new VersionCompatibilityChecker($moduleVersions);
        $result = $checker->checkCompatibility();

        self::assertTrue($result->isCompatible);
    }

    public function test_tilde_version_incompatible(): void
    {
        $moduleVersions = [
            'Product' => new TModuleVersion(
                moduleName: 'Product',
                version: '1.0.0',
                requiredModules: ['Catalog' => '~1.2'],
            ),
            'Catalog' => new TModuleVersion(
                moduleName: 'Catalog',
                version: '1.3.0',
            ),
        ];

        $checker = new VersionCompatibilityChecker($moduleVersions);
        $result = $checker->checkCompatibility();

        self::assertFalse($result->isCompatible);
    }

    public function test_greater_than_or_equal_operator(): void
    {
        $moduleVersions = [
            'Product' => new TModuleVersion(
                moduleName: 'Product',
                version: '1.0.0',
                requiredModules: ['Catalog' => '>=1.2'],
            ),
            'Catalog' => new TModuleVersion(
                moduleName: 'Catalog',
                version: '1.5.0',
            ),
        ];

        $checker = new VersionCompatibilityChecker($moduleVersions);
        $result = $checker->checkCompatibility();

        self::assertTrue($result->isCompatible);
    }

    public function test_exact_version_match(): void
    {
        $moduleVersions = [
            'Product' => new TModuleVersion(
                moduleName: 'Product',
                version: '1.0.0',
                requiredModules: ['Catalog' => '1.2.0'],
            ),
            'Catalog' => new TModuleVersion(
                moduleName: 'Catalog',
                version: '1.2.0',
            ),
        ];

        $checker = new VersionCompatibilityChecker($moduleVersions);
        $result = $checker->checkCompatibility();

        self::assertTrue($result->isCompatible);
    }

    public function test_exact_version_mismatch(): void
    {
        $moduleVersions = [
            'Product' => new TModuleVersion(
                moduleName: 'Product',
                version: '1.0.0',
                requiredModules: ['Catalog' => '1.2.0'],
            ),
            'Catalog' => new TModuleVersion(
                moduleName: 'Catalog',
                version: '1.2.1',
            ),
        ];

        $checker = new VersionCompatibilityChecker($moduleVersions);
        $result = $checker->checkCompatibility();

        self::assertFalse($result->isCompatible);
    }

    public function test_multiple_modules_with_errors(): void
    {
        $moduleVersions = [
            'User' => new TModuleVersion(
                moduleName: 'User',
                version: '1.2.0',
                requiredModules: ['Auth' => '^2.0', 'Logger' => '^1.0'],
            ),
            'Product' => new TModuleVersion(
                moduleName: 'Product',
                version: '2.0.0',
                requiredModules: ['User' => '^2.0'],
            ),
            'Auth' => new TModuleVersion(
                moduleName: 'Auth',
                version: '1.5.0',
            ),
        ];

        $checker = new VersionCompatibilityChecker($moduleVersions);
        $result = $checker->checkCompatibility();

        self::assertFalse($result->isCompatible);
        self::assertGreaterThanOrEqual(2, count($result->errors));
    }

    public function test_empty_module_versions(): void
    {
        $checker = new VersionCompatibilityChecker([]);
        $result = $checker->checkCompatibility();

        self::assertTrue($result->isCompatible);
        self::assertEmpty($result->errors);
    }

    public function test_module_without_dependencies(): void
    {
        $moduleVersions = [
            'Auth' => new TModuleVersion(
                moduleName: 'Auth',
                version: '1.5.0',
            ),
            'Logger' => new TModuleVersion(
                moduleName: 'Logger',
                version: '2.0.0',
            ),
        ];

        $checker = new VersionCompatibilityChecker($moduleVersions);
        $result = $checker->checkCompatibility();

        self::assertTrue($result->isCompatible);
        self::assertEmpty($result->errors);
    }
}
