<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\FileContent\StubFiles;

use function count;
use function file_get_contents;
use function glob;
use function in_array;
use function is_dir;
use function is_file;
use function is_string;
use function sprintf;
use function str_contains;
use function strlen;
use function substr;

/**
 * The stubs a project published, checked against what the scaffolder will do
 * with them.
 *
 * A published stub is a copy that stops tracking its original -- the failure
 * mode of the whole feature. Two things are mechanically knowable and both are
 * silent otherwise: a stub that lost a placeholder generates a broken class,
 * and a stub filed under a name nothing reads is an edit that never takes
 * effect.
 */
final class StubHealthCheck implements HealthCheck
{
    /** @var list<string> */
    private const REQUIRED_PLACEHOLDERS = ['$NAMESPACE$', '$CLASS_NAME$'];

    /**
     * @param array<string, string> $published stub file (relative) => contents
     */
    public function __construct(
        private readonly string $stubsDir,
        private readonly array $published,
    ) {
    }

    public function name(): string
    {
        return 'published stubs';
    }

    public function run(): CheckResult
    {
        if (!is_dir($this->stubsDir)) {
            return CheckResult::ok($this->name(), 'no stubs published — the built-in templates are in use');
        }

        $problems = $this->problems();
        if ($problems === []) {
            return CheckResult::ok($this->name(), sprintf('%d published stub(s), all usable', count($this->published)));
        }

        return CheckResult::warn(
            $this->name(),
            $problems,
            'Fix the stub, or delete it to fall back to the built-in template.',
        );
    }

    /**
     * Every file actually published, whatever it is named -- the unknown ones
     * are the point.
     *
     * @return array<string, string> stub file (relative) => contents
     */
    public static function readPublished(string $stubsDir): array
    {
        if (!is_dir($stubsDir)) {
            return [];
        }

        $published = [];

        foreach ([...StubFiles::all(), ...self::strayFiles($stubsDir)] as $stubFile) {
            $path = $stubsDir . '/' . $stubFile;
            if (!is_file($path)) {
                continue;
            }

            $contents = file_get_contents($path);
            $published[$stubFile] = is_string($contents) ? $contents : '';
        }

        return $published;
    }

    /**
     * @return list<string>
     */
    private function problems(): array
    {
        $known = StubFiles::all();
        $problems = [];

        foreach ($this->published as $stubFile => $contents) {
            if (!in_array($stubFile, $known, true)) {
                $problems[] = sprintf(
                    '%s matches no template the scaffolder reads, so editing it changes nothing',
                    $stubFile,
                );
                continue;
            }

            foreach (self::REQUIRED_PLACEHOLDERS as $placeholder) {
                if (!str_contains($contents, $placeholder)) {
                    $problems[] = sprintf('%s no longer contains %s, so it would generate a broken class', $stubFile, $placeholder);
                }
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private static function strayFiles(string $stubsDir): array
    {
        $stray = [];

        foreach (glob($stubsDir . '/{,*/}*.txt', GLOB_BRACE) ?: [] as $path) {
            $relative = substr($path, strlen($stubsDir) + 1);
            if (!in_array($relative, StubFiles::all(), true)) {
                $stray[] = $relative;
            }
        }

        return $stray;
    }
}
