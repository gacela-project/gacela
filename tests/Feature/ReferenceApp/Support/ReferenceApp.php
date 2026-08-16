<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Support;

use Closure;
use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Gacela\Framework\Health\HealthCheckRegistry;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Infrastructure\ResolverActivityLog;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\AttemptId;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Clock\FixedClock;
use GacelaTest\Feature\Util\DirectoryUtil;
use RuntimeException;

use function dirname;
use function sprintf;

/**
 * How the three harness classes boot the reference application.
 *
 * The host's half of the contract lives here: the application asks for a clock
 * through `addExternalService()`, and every caller -- these tests, and a real
 * host framework -- has to answer. Passing a fixed one is what makes an
 * assertion about an invoice date possible.
 */
final class ReferenceApp
{
    public const FIXED_TODAY = '2026-08-16';

    /**
     * Every temp directory this harness makes starts with it, and nothing is
     * removed that does not.
     */
    private const TEMP_PREFIX = 'gacela-reference-app-';

    /**
     * The application root: the directory holding `gacela.php`, `config/` and
     * the five modules.
     */
    public static function root(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Invoicing';
    }

    public static function rulesFile(): string
    {
        return self::root() . DIRECTORY_SEPARATOR . 'module-rules.json';
    }

    /**
     * Bootstrap with the closure *and* `gacela.php`, which is the arrangement a
     * host framework uses: the closure supplies what only the host knows, the
     * file decides everything else, and both contribute.
     *
     * @param Closure(GacelaConfig):void|null $extra
     */
    public static function bootstrap(?Closure $extra = null): void
    {
        Gacela::bootstrap(self::root(), static function (GacelaConfig $config) use ($extra): void {
            $config->resetInMemoryCache();
            $config->addExternalService('clock', new FixedClock(self::FIXED_TODAY));

            if ($extra instanceof Closure) {
                $extra($config);
            }
        });
    }

    /**
     * A directory this run owns, under the system temp directory.
     *
     * Everything the harness writes -- warmed caches, published stubs,
     * generated editor metadata, a scaffolded throwaway project -- goes into
     * one of these and nowhere near the application, whose only writable
     * artefacts are the two generated DTO classes, and those are committed
     * source.
     */
    public static function createTempDirectory(string $purpose): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . self::TEMP_PREFIX . $purpose . '-' . bin2hex(random_bytes(4));

        mkdir($directory, 0o777, true);

        return $directory;
    }

    /**
     * Remove a directory {@see createTempDirectory()} handed out, and only such
     * a directory: the prefix is checked before anything is deleted, so a path
     * that arrived from somewhere else takes the test down instead of the tree.
     */
    public static function removeTempDirectory(string $directory): void
    {
        $expectedPrefix = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::TEMP_PREFIX;

        if (!str_starts_with($directory, $expectedPrefix)) {
            throw new RuntimeException(sprintf(
                'Refusing to remove "%s": it is not a directory this harness created under "%s".',
                $directory,
                $expectedPrefix,
            ));
        }

        DirectoryUtil::removeDir($directory);
    }

    /**
     * Everything this application keeps outside a container, put back.
     *
     * Named one by one rather than swept: each entry is state some feature of
     * the application deliberately holds for the life of the process, and a
     * test that leaves one behind changes the next one's answer.
     */
    public static function reset(): void
    {
        Gacela::resetCache();
        CacheableConfig::reset();
        HealthCheckRegistry::reset();
        ResolverActivityLog::reset();
        AttemptId::reset();

        putenv('APP_ENV');
        putenv('APP_REGION');
        putenv('GACELA_CACHE_DIR');
    }
}
