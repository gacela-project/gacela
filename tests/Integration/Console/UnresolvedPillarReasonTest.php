<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console;

use Gacela\Console\Application\Doctor\Check\UnresolvedPillarFileCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Domain\AllAppModules\AppModuleCreator;
use Gacela\Console\Domain\AllAppModules\PillarResolutionFailure;
use Gacela\Container\Exception\DependencyNotFoundException;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

/**
 * A Factory whose own constructor dependency cannot be satisfied.
 *
 * Everything about the file is right -- the `namespace` matches its directory,
 * the class loads, the psr-4 prefix resolves -- and resolution still comes back
 * `null`, because `AppModuleCreator` catches whatever was thrown and reports the
 * pillar as absent. `doctor` then printed "the file is there and nothing can
 * load it -- check the `namespace` declaration", which was the one thing that
 * had already been checked. That advice cost a real debugging session (#884).
 *
 * The unit tests pin the exact wording from a controlled exception; what this
 * one is for is the exception being a real one, thrown from where the incident
 * threw it, and surviving the whole way to the printed line.
 */
final class UnresolvedPillarReasonTest extends TestCase
{
    private const NAMESPACE_PREFIX = 'TempPillarReason';

    private string $appRoot = '';

    private string $moduleDir = '';

    /** @var list<string> */
    private array $created = [];

    protected function setUp(): void
    {
        $this->appRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-pillar-reason-' . bin2hex(random_bytes(4));
        $this->moduleDir = $this->appRoot . DIRECTORY_SEPARATOR . 'Blog';
        mkdir($this->moduleDir, 0777, true);

        Gacela::bootstrap(dirname(__DIR__, 3));
    }

    protected function tearDown(): void
    {
        // Names exactly what this test created.
        foreach ($this->created as $file) {
            self::assertStringStartsWith($this->moduleDir . DIRECTORY_SEPARATOR, $file);
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach ([$this->moduleDir, $this->appRoot] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }

        $this->created = [];
        Gacela::resetCache();
    }

    public function test_a_factory_that_cannot_build_is_reported_with_what_actually_failed(): void
    {
        $namespace = $this->writeModule();

        $module = $this->creator()->fromClass($namespace . '\\BlogFacade');

        // Unchanged: nothing resolved, so `list:modules` still prints a blank
        // cell. What the reason is for is the check below.
        self::assertNull($module->factoryClass());

        $failure = $module->resolutionFailure('Factory');
        self::assertInstanceOf(PillarResolutionFailure::class, $failure);
        self::assertSame(DependencyNotFoundException::class, $failure->exceptionClass);

        // Only the kind that threw. The Config and the Provider of this module
        // do not exist at all, which is an ordinary module and not a failure.
        self::assertNull($module->resolutionFailure('Config'));
        self::assertNull($module->resolutionFailure('Provider'));

        $result = (new UnresolvedPillarFileCheck([$module]))->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertCount(1, $result->details);
        self::assertStringContainsString(
            'BlogFactory.php is on disk and no Factory resolved',
            $result->details[0],
        );
        self::assertStringContainsString(DependencyNotFoundException::class, $result->details[0]);
        self::assertStringContainsString($namespace . '\\BlogCollaborator', $result->details[0]);

        // The claim that cost the debugging session, gone: this file's namespace
        // and psr-4 prefix are both fine, and nothing tells the reader otherwise.
        self::assertStringNotContainsString('nothing can load it', $result->remediation);
        self::assertStringNotContainsString('dump-autoload', $result->remediation);
        self::assertStringContainsString('fix the failure named on each line', $result->remediation);
    }

    /**
     * Two pillars of one module, each unable to build for its own reason. One
     * failure per kind or the second is a second round trip -- and a reader who
     * fixes the Factory only to be told nothing about the Provider is back
     * where the misleading single message left them.
     */
    public function test_two_pillars_that_both_fail_each_keep_their_own_reason(): void
    {
        $namespace = $this->writeModule(withFailingProvider: true);

        $module = $this->creator()->fromClass($namespace . '\\BlogFacade');

        foreach (['Factory', 'Provider'] as $kind) {
            $failure = $module->resolutionFailure($kind);
            self::assertInstanceOf(PillarResolutionFailure::class, $failure, $kind . ' recorded no failure');
            self::assertSame(DependencyNotFoundException::class, $failure->exceptionClass);
        }

        $details = (new UnresolvedPillarFileCheck([$module]))->run()->details;

        self::assertCount(2, $details);
        foreach ($details as $detail) {
            self::assertStringContainsString(DependencyNotFoundException::class, $detail);
        }
    }

    /**
     * @return non-empty-string the namespace the module was written into
     */
    private function writeModule(bool $withFailingProvider = false): string
    {
        $namespace = self::NAMESPACE_PREFIX . bin2hex(random_bytes(4)) . '\\Blog';

        $this->writeFile('BlogFacade.php', <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            /**
             * @extends \\Gacela\\Framework\\AbstractFacade<BlogFactory>
             */
            final class BlogFacade extends \\Gacela\\Framework\\AbstractFacade
            {
            }
            PHP);

        $this->writeFile('BlogFactory.php', <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            interface BlogCollaborator
            {
            }

            /**
             * @extends \\Gacela\\Framework\\AbstractFactory<\\Gacela\\Framework\\AbstractConfig>
             */
            final class BlogFactory extends \\Gacela\\Framework\\AbstractFactory
            {
                public function __construct(private readonly BlogCollaborator \$collaborator)
                {
                }
            }
            PHP);

        if ($withFailingProvider) {
            $this->writeFile('BlogProvider.php', <<<PHP
                <?php

                declare(strict_types=1);

                namespace {$namespace};

                final class BlogProvider extends \\Gacela\\Framework\\AbstractProvider
                {
                    public function __construct(private readonly BlogCollaborator \$collaborator)
                    {
                    }

                    public function provideModuleDependencies(\\Gacela\\Framework\\Container\\Container \$container): void
                    {
                    }
                }
                PHP);
        }

        return $namespace;
    }

    private function writeFile(string $filename, string $contents): void
    {
        $path = $this->moduleDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $contents);
        $this->created[] = $path;

        require_once $path;
    }

    private function creator(): AppModuleCreator
    {
        return new AppModuleCreator(new FactoryResolver(), new ConfigResolver(), new ProviderResolver());
    }
}
