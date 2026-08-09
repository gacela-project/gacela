<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileContent;

use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;

use function array_values;
use function in_array;

/**
 * Which file on disk holds the stub for which generated pillar.
 *
 * The scaffolder's templates ship as `.txt` files, and publishing them is
 * copying those files somewhere a project can edit them. The names have to
 * match in both directions -- a published stub is found by the same name it was
 * written under -- so the mapping is stated once, here.
 */
final class StubFiles
{
    public const SERVICE_SUBDIRECTORY = 'service';

    /**
     * @return array<string, string> generated filename => stub file, relative to the stubs directory
     */
    public static function basic(): array
    {
        return [
            FilenameSanitizer::FACADE => 'facade-maker.txt',
            FilenameSanitizer::FACTORY => 'factory-maker.txt',
            FilenameSanitizer::CONFIG => 'config-maker.txt',
            FilenameSanitizer::PROVIDER => 'provider-maker.txt',
        ];
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
     * Every stub a project can publish, each named once however many templates
     * use it.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $files = [];

        foreach ([...array_values(self::basic()), ...array_values(self::service())] as $file) {
            if (!in_array($file, $files, true)) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
