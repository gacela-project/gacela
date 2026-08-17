<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

use function class_exists;
use function file_get_contents;
use function implode;
use function preg_match_all;
use function realpath;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;

/**
 * Every read of a non-`final` attribute must pass `ReflectionAttribute::IS_INSTANCEOF`.
 *
 * An attribute is left non-`final` for exactly one reason: so a consumer can
 * subclass it to re-present it under their own namespace.
 * `Gacela\Container\Attribute\Inject` says so in its docblock, and
 * `Gacela\Framework\Attribute\Inject` is the subclass gacela itself ships.
 *
 * A reader that names the parent class without the flag matches the parent and
 * nothing else, so every subclass is dropped. Nothing raises: the parameter is
 * simply never injected, the `debug:*` output shows plain autowiring, the
 * Symfony bridge lets Symfony autowire instead. That is the failure both
 * attribute docblocks call out as silent, and it shipped in two readers anyway,
 * because the container's own three call sites were correct and the tests only
 * ever exercised the parent spelling.
 *
 * Reading a `final` attribute exactly is fine, and this test says nothing about
 * it. The rule is tied to subclassability, so making an attribute non-`final`
 * later brings its readers under the rule automatically.
 */
final class AttributeReadCoverageTest extends TestCase
{
    /** @var list<string> */
    private const array ROOTS = [
        __DIR__ . '/../../../src',
        __DIR__ . '/../../../bridges/symfony-bridge/src',
    ];

    public function test_every_non_final_attribute_is_read_with_is_instanceof(): void
    {
        $offenders = [];
        $checked = 0;

        foreach ($this->attributeReads() as $read) {
            $reflection = new ReflectionClass($read['attribute']);
            if ($reflection->isFinal()) {
                continue;
            }

            ++$checked;
            if (!str_contains($read['args'], 'IS_INSTANCEOF')) {
                $offenders[] = sprintf(
                    '%s reads non-final %s without ReflectionAttribute::IS_INSTANCEOF',
                    $read['file'],
                    $read['attribute'],
                );
            }
        }

        self::assertNotSame(0, $checked, 'Found no reads of a non-final attribute; the scanner is broken.');
        self::assertSame([], $offenders, implode("\n", $offenders));
    }

    /**
     * @return list<array{file: string, attribute: class-string, args: string}>
     */
    private function attributeReads(): array
    {
        $reads = [];

        foreach ($this->sourceFiles() as $file) {
            $code = (string) file_get_contents($file);
            if (!str_contains($code, 'getAttributes(')) {
                continue;
            }

            $imports = $this->importsOf($code);

            preg_match_all('/getAttributes\(\s*([\\\\\w]+)::class\s*([^;]*?)\)/s', $code, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $attribute = $this->resolve($match[1], $imports);
                if ($attribute === null) {
                    continue;
                }

                $reads[] = ['file' => $this->relative($file), 'attribute' => $attribute, 'args' => $match[2]];
            }
        }

        return $reads;
    }

    /**
     * @param array<string, string> $imports
     *
     * @return class-string|null
     */
    private function resolve(string $name, array $imports): ?string
    {
        $candidate = str_starts_with($name, '\\') ? substr($name, 1) : ($imports[$name] ?? $name);

        /** @var class-string $candidate */
        return class_exists($candidate) ? $candidate : null;
    }

    /**
     * Short name to FQCN, for the `use` statements of one file.
     *
     * @return array<string, string>
     */
    private function importsOf(string $code): array
    {
        $imports = [];

        preg_match_all('/^use\s+([\\\\\w]+)(?:\s+as\s+(\w+))?\s*;/m', $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $fqcn = $match[1];
            $alias = $match[2] ?? substr($fqcn, (int) strrpos($fqcn, '\\') + 1);
            $imports[$alias] = $fqcn;
        }

        return $imports;
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (self::ROOTS as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator((string) realpath($root)));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function relative(string $path): string
    {
        $root = (string) realpath(__DIR__ . '/../../..');

        return str_starts_with($path, $root) ? substr($path, strlen($root) + 1) : $path;
    }
}
