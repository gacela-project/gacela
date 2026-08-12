<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileContent;

use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;

use function array_values;
use function in_array;
use function preg_replace;
use function strtolower;

/**
 * Which file on disk holds the stub for which generated class.
 *
 * The scaffolder's templates ship as `.txt` files, and publishing them is
 * copying those files somewhere a project can edit them. The names have to
 * match in both directions -- a published stub is found by the same name it was
 * written under -- so the mapping is stated once, here.
 *
 * A kind the project declared is named by the same rule the shipped files
 * follow, `Exporter` => `exporter-maker.txt`. Nothing ships under that name:
 * the stub a project writes there is the only template such a kind can have,
 * which is why a declared kind is generatable exactly when it is published.
 */
final class StubFiles
{
    public const SERVICE_SUBDIRECTORY = 'service';

    /**
     * @param list<string> $declaredKinds the kinds this project declared, beyond the pillars
     *
     * @return array<string, string> generated filename => stub file, relative to the stubs directory
     */
    public static function basic(array $declaredKinds = []): array
    {
        $files = [
            FilenameSanitizer::FACADE => 'facade-maker.txt',
            FilenameSanitizer::FACTORY => 'factory-maker.txt',
            FilenameSanitizer::CONFIG => 'config-maker.txt',
            FilenameSanitizer::PROVIDER => 'provider-maker.txt',
        ];

        foreach ($declaredKinds as $kind) {
            $files[$kind] = self::stubFileFor($kind);
        }

        return $files;
    }

    /**
     * The file a kind's stub is published as: lower case, hyphenated where the
     * name changes case, which is what the shipped names already look like.
     */
    public static function stubFileFor(string $kind): string
    {
        $hyphenated = preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $kind);

        return strtolower($hyphenated ?? $kind) . '-maker.txt';
    }

    /**
     * @return array<string, string>
     */
    public static function service(): array
    {
        return [
            FilenameSanitizer::FACADE => self::SERVICE_SUBDIRECTORY . '/facade-maker.txt',
            FilenameSanitizer::FACTORY => self::SERVICE_SUBDIRECTORY . '/factory-maker.txt',
            FilenameSanitizer::CONFIG => 'config-maker.txt',
            FilenameSanitizer::PROVIDER => 'provider-maker.txt',
            'Service' => self::SERVICE_SUBDIRECTORY . '/service-maker.txt',
            'FacadeTest' => self::SERVICE_SUBDIRECTORY . '/facade-test-maker.txt',
        ];
    }

    /**
     * Every stub the scaffolder reads, each named once however many templates
     * use it.
     *
     * @param list<string> $declaredKinds
     *
     * @return list<string>
     */
    public static function all(array $declaredKinds = []): array
    {
        $files = [];

        foreach ([...array_values(self::basic($declaredKinds)), ...array_values(self::service())] as $file) {
            if (!in_array($file, $files, true)) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
