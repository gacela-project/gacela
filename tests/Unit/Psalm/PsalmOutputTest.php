<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use GacelaTest\Integration\Psalm\PsalmOutput;
use GacelaTest\Integration\Psalm\ReferenceAppDiagnostics;
use PHPUnit\Framework\TestCase;

final class PsalmOutputTest extends TestCase
{
    public function test_stderr_cannot_corrupt_json_or_hide_from_raw_checks(): void
    {
        $script = 'fwrite(STDOUT, "[]"); fwrite(STDERR, "ServiceMapAccessors is internal");';
        $output = PsalmOutput::fromCommand(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script));

        self::assertSame('[]', $output->stdout);
        self::assertSame('ServiceMapAccessors is internal', $output->stderr);
        self::assertSame([], ReferenceAppDiagnostics::in($output->stdout));
        self::assertStringContainsString('ServiceMapAccessors is internal', $output->combined());
    }

    public function test_a_late_process_failure_cannot_pass_with_valid_json(): void
    {
        $script = 'fwrite(STDOUT, "[]"); fwrite(STDERR, "late fatal error"); exit(1);';
        $output = PsalmOutput::fromCommand(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script));

        self::assertSame(1, $output->exitCode);
        self::assertFalse($output->isCompleteAnalysis());
    }

    public function test_the_normal_findings_exit_status_is_a_completed_analysis(): void
    {
        $output = PsalmOutput::fromCommand(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('echo "[]"; exit(2);'));

        self::assertSame(2, $output->exitCode);
        self::assertTrue($output->isCompleteAnalysis());
    }

}
