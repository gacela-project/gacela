<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Gacela;

use Composer\InstalledVersions;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function test_version_is_derived_from_the_installed_package(): void
    {
        self::assertSame(
            InstalledVersions::getPrettyVersion('gacela-project/gacela'),
            Gacela::version(),
        );
    }

    public function test_version_is_not_a_hardcoded_empty_or_stale_literal(): void
    {
        $version = Gacela::version();

        self::assertNotSame('', $version);
        self::assertNotSame('1.16.0', $version);
    }
}
