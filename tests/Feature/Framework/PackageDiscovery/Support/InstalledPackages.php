<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PackageDiscovery\Support;

use PHPUnit\Framework\Assert;
use RuntimeException;

use function array_reverse;
use function bin2hex;
use function dirname;
use function file_put_contents;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function str_starts_with;
use function sys_get_temp_dir;
use function unlink;

/**
 * An application root with packages installed against it.
 *
 * The root is a throwaway directory, because each test wants a different
 * `gacela.php` beside the same packages. The packages themselves are committed
 * under `Packages/`, and the `installed.json` written here is *generated from
 * their own manifests* -- so a fixture package declares `extra.gacela.config`
 * in exactly one place, the way a real one does, and this class only plays the
 * part `composer install` plays.
 */
final class InstalledPackages
{
    private const string PREFIX = 'gacela-package-discovery-';

    public readonly string $appRoot;

    /** @var list<string> every file this created, in creation order */
    private array $files = [];

    /** @var list<string> every directory this created, in creation order */
    private array $directories = [];

    public function __construct()
    {
        $this->appRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::PREFIX . bin2hex(random_bytes(5));
        $this->makeDirectory($this->appRoot);
    }

    /**
     * @param list<string> $packageDirectories directory names under `Packages/`,
     *                                         in the order Composer would have
     *                                         installed them
     */
    public function install(array $packageDirectories): void
    {
        $packages = [];

        foreach ($packageDirectories as $directory) {
            $packageRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . $directory;
            $manifest = $packageRoot . DIRECTORY_SEPARATOR . 'composer.json';

            if (!is_file($manifest)) {
                throw new RuntimeException(sprintf('No fixture package at "%s".', $manifest));
            }

            /** @var array{name: string, extra: array<string, mixed>} $decoded */
            $decoded = (array) json_decode((string) file_get_contents($manifest), true);

            $packages[] = [
                'name' => $decoded['name'],
                'extra' => $decoded['extra'],
                // Composer writes this relative to `vendor/composer`. Absolute is
                // as valid, and it is what lets the packages stay committed while
                // the root that installs them is a temp directory. The relative
                // form is covered by PackageConfigFinderTest.
                'install-path' => $packageRoot,
            ];
        }

        $this->write(
            $this->appRoot . DIRECTORY_SEPARATOR . 'vendor'
                . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json',
            (string) json_encode(['packages' => $packages, 'dev' => true]),
        );
    }

    /**
     * The application's own configuration -- the file that must not have to
     * mention any of the packages above.
     */
    public function writeGacelaPhp(string $body): void
    {
        $this->write(
            $this->appRoot . DIRECTORY_SEPARATOR . 'gacela.php',
            "<?php\n\ndeclare(strict_types=1);\n\n" . $body,
        );
    }

    public function cacheDir(): string
    {
        $dir = $this->appRoot . DIRECTORY_SEPARATOR . 'cache';
        $this->makeDirectory($dir);

        return $dir;
    }

    /**
     * Removes exactly what was created, named one path at a time and each
     * asserted to sit under the temp root this built.
     */
    public function remove(): void
    {
        foreach (array_reverse($this->files) as $file) {
            $this->assertOwned($file);

            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach (array_reverse($this->directories) as $directory) {
            $this->assertOwned($directory);

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }

        $this->files = [];
        $this->directories = [];
    }

    /**
     * A file discovery wrote, so `remove()` can name it too.
     */
    public function alsoRemove(string $file): void
    {
        $this->files[] = $file;
    }

    private function write(string $path, string $contents): void
    {
        $this->makeDirectory(dirname($path));

        file_put_contents($path, $contents);
        $this->files[] = $path;
    }

    private function makeDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        // Recorded innermost-last so the reversal below removes children first.
        $parent = dirname($directory);

        if (str_starts_with($parent, $this->appRoot) || $parent === $this->appRoot) {
            $this->makeDirectory($parent);
        }

        mkdir($directory);
        $this->directories[] = $directory;
    }

    private function assertOwned(string $path): void
    {
        Assert::assertStringStartsWith(
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::PREFIX,
            $path,
            'refusing to remove a path this fixture did not create',
        );
    }
}
