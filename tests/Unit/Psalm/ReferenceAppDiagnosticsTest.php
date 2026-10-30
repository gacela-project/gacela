<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use GacelaTest\Integration\Psalm\ReferenceAppDiagnostics;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReferenceAppDiagnosticsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function separators(): iterable
    {
        yield 'POSIX' => ['/'];
        yield 'Windows' => ['\\'];
    }

    #[DataProvider('separators')]
    public function test_only_the_diagnostic_file_selects_an_application_finding(string $separator): void
    {
        $framework = ['file_path' => str_replace('/', $separator, '/repo/src/Framework/ServiceResolverAwareTrait.php'),
            'message' => 'The class GacelaTest\\Feature\\ReferenceApp\\Invoicing\\Reporting\\ReportingFactory uses an internal helper'];
        $application = ['file_path' => str_replace('/', $separator, '/repo/tests/Feature/ReferenceApp/Invoicing/Reporting/ReportingFactory.php'),
            'message' => 'An application finding'];
        $json = json_encode([$framework, $application], JSON_THROW_ON_ERROR);
        $findings = ReferenceAppDiagnostics::in($json);

        self::assertCount(1, $findings);
        self::assertSame($application['file_path'], $findings[0]->file_path);
        self::assertSame($application['message'], $findings[0]->message);
    }
}
