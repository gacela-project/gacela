<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\CheckStatus;
use PHPUnit\Framework\TestCase;

final class CheckResultTest extends TestCase
{
    public function test_ok_has_empty_details_when_no_detail_passed(): void
    {
        $result = CheckResult::ok('title');

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame('title', $result->title);
        self::assertSame([], $result->details);
        self::assertSame('', $result->remediation);
    }

    public function test_ok_wraps_detail_string_into_list(): void
    {
        $result = CheckResult::ok('title', 'single detail');

        self::assertSame(['single detail'], $result->details);
    }

    /**
     * Not every finding is a problem: a check that reports which files a rule of
     * the framework's applies to has to name them and say what to do if one is
     * there by coincidence, without failing `doctor --strict`.
     */
    public function test_ok_preserves_several_details_and_a_remediation(): void
    {
        $result = CheckResult::ok('title', ['a', 'b'], 'rename it');

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['a', 'b'], $result->details);
        self::assertSame('rename it', $result->remediation);
    }

    public function test_ok_has_empty_details_when_an_empty_list_is_passed(): void
    {
        $result = CheckResult::ok('title', []);

        self::assertSame([], $result->details);
    }

    public function test_warn_preserves_details_and_remediation(): void
    {
        $result = CheckResult::warn('title', ['a', 'b'], 'fix it');

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['a', 'b'], $result->details);
        self::assertSame('fix it', $result->remediation);
    }

    public function test_error_preserves_details_and_remediation(): void
    {
        $result = CheckResult::error('title', ['bad'], 'do this');

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertSame(['bad'], $result->details);
        self::assertSame('do this', $result->remediation);
    }
}
