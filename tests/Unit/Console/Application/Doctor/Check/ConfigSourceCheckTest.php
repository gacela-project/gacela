<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\ConfigSourceCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use PHPUnit\Framework\TestCase;

final class ConfigSourceCheckTest extends TestCase
{
    public function test_a_project_declaring_no_config_has_nothing_to_load(): void
    {
        $result = (new ConfigSourceCheck([], 0))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['no config paths declared — nothing to load'], $result->details);
    }

    public function test_declared_paths_that_all_match_pass(): void
    {
        $result = (new ConfigSourceCheck([], 2))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['2 declared config path(s) match files'], $result->details);
    }

    /**
     * The typo this exists for: `conf/` where the directory is `config/`.
     */
    public function test_a_path_matching_nothing_is_named(): void
    {
        $result = (new ConfigSourceCheck(['/app/conf/*.php'], 1))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['/app/conf/*.php matches no file'], $result->details);
        self::assertStringContainsString('1 of 1', $result->remediation);
        self::assertStringContainsString('addAppConfig()', $result->remediation);
    }

    public function test_every_unmatched_path_is_named_not_only_the_first(): void
    {
        $result = (new ConfigSourceCheck(['/app/conf/*.php', '/app/settings/*.php'], 3))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame([
            '/app/conf/*.php matches no file',
            '/app/settings/*.php matches no file',
        ], $result->details);
        self::assertStringContainsString('2 of 3', $result->remediation);
    }
}
