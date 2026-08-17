<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModules;

use Gacela\Console\Infrastructure\Command\DebugModulesCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\DebugModules\PillarFixtures\DefinedModule\DefinedContract;
use GacelaTest\Feature\Console\DebugModules\PillarFixtures\DefinedModule\DefinedImplementation;
use GacelaTest\Feature\Console\DebugModules\PillarFixtures\DefinedModule\DefinedModuleFacade;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `--check` answers one question -- "can the class resolver build this pillar?"
 * -- so it has to ask the container that does the building.
 *
 * It used to read `Gacela::container()`, which is configured from the same
 * declarations and therefore usually agrees. Usually is not the promise an exit
 * code makes: where the two part company, the answer came from the container
 * that was not going to build anything.
 */
final class PillarCheckHonestyTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    /**
     * A definition satisfies a pillar constructor, so `--check` passes -- and
     * the pillar really does build, which is what makes the exit code true
     * rather than merely green.
     */
    public function test_check_passes_for_a_pillar_a_definition_satisfies(): void
    {
        $tester = $this->debugModules(static function (GacelaConfig $config): void {
            $config->loadDefinitions([DefinedContract::class => ['singleton' => DefinedImplementation::class]]);
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Every inspected parameter can be satisfied', $tester->getDisplay());
        self::assertInstanceOf(DefinedImplementation::class, (new DefinedModuleFacade())->contract());
    }

    /**
     * Registering on the application container after bootstrap is the plainest
     * way the two containers can disagree: the class resolver is configured
     * from `gacela.php`, and never sees it. A pillar needing it cannot be
     * built, and `--check` has to say so.
     */
    public function test_check_fails_for_a_pillar_only_the_app_container_can_satisfy(): void
    {
        $tester = $this->debugModules(static function (GacelaConfig $config): void {
            // Nothing declares DefinedContract here.
            $config->setFileCache(false);
        }, static function (): void {
            Gacela::container()->bind(DefinedContract::class, DefinedImplementation::class);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('the container cannot satisfy', $tester->getDisplay());
    }

    /**
     * @param callable(GacelaConfig):void $configure
     * @param (callable():void)|null $afterBootstrap
     */
    private function debugModules(callable $configure, ?callable $afterBootstrap = null): CommandTester
    {
        Gacela::bootstrap(
            __DIR__ . DIRECTORY_SEPARATOR . 'PillarFixtures',
            static function (GacelaConfig $config) use ($configure): void {
                $config->resetInMemoryCache();
                $configure($config);
            },
        );

        if ($afterBootstrap !== null) {
            $afterBootstrap();
        }

        $tester = new CommandTester(new DebugModulesCommand());
        $tester->execute(['--check' => true]);

        return $tester;
    }
}
