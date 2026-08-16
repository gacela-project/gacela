<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\EventListenersAcrossBootstrap;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

/**
 * Listeners registered in a bootstrap closure and in `gacela.php` at the same
 * time -- the ordinary composition, an application bootstrap plus a project
 * config file.
 *
 * `Gacela::bootstrap()` builds the setup from the closure and merges
 * `gacela.php` in later, during `Config::init()`. Two things on that path used
 * to lose listeners, and they showed up in opposite arrangements, so either one
 * alone looked like the whole story:
 *
 * - the merge *replaced* the generic listeners instead of appending, so
 *   whichever side was merged into lost its own
 * - the bootstrap dispatches an event before `init()`, memoizing the pre-merge
 *   dispatcher, so when the merge installed a different one every later
 *   dispatch still went to the old object
 *
 * Nothing failed either way. The listener was registered and never ran, which
 * is the worst thing a hook API can do.
 */
final class EventListenersAcrossBootstrapTest extends TestCase
{
    private string $appRoot = '';

    protected function setUp(): void
    {
        $this->appRoot = sys_get_temp_dir() . '/gacela-events-' . bin2hex(random_bytes(6));
        mkdir($this->appRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        // Names exactly what this test created.
        $gacelaFile = $this->appRoot . '/gacela.php';
        if (is_dir($this->appRoot)) {
            if (file_exists($gacelaFile)) {
                unlink($gacelaFile);
            }

            rmdir($this->appRoot);
        }

        unset($GLOBALS['gacela_test_events']);
        Gacela::resetCache();
    }

    public function test_a_generic_listener_from_each_side_fires(): void
    {
        $this->writeGacelaFileRegisteringAGenericListener();

        Gacela::bootstrap($this->appRoot, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->registerGenericListener(static function (GacelaEventInterface $event): void {
                $GLOBALS['gacela_test_events']['closure'] = true;
            });
        });

        $this->dispatchSomething();

        self::assertTrue($GLOBALS['gacela_test_events']['closure'] ?? false, 'the closure listener never fired');
        self::assertTrue($GLOBALS['gacela_test_events']['file'] ?? false, 'the gacela.php listener never fired');
    }

    /**
     * The arrangement that exposed the stale memo rather than the replace: with
     * the closure registering none, the merge builds a *different* dispatcher,
     * and the one the bootstrap event memoized is no longer it.
     */
    public function test_a_gacela_file_listener_fires_when_the_closure_registers_none(): void
    {
        $this->writeGacelaFileRegisteringAGenericListener();

        Gacela::bootstrap($this->appRoot, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $this->dispatchSomething();

        self::assertTrue($GLOBALS['gacela_test_events']['file'] ?? false, 'the gacela.php listener never fired');
    }

    /**
     * Counted after bootstrap on purpose. The pre-merge dispatcher fires during
     * bootstrap itself, so a flag set before this point says nothing about
     * whether the listener is still reachable afterwards -- which is exactly
     * how the stale memo stayed hidden.
     */
    private function dispatchSomething(): void
    {
        $GLOBALS['gacela_test_events'] = [];

        Config::getInstance()->get('a-key-that-does-not-exist', 'default');
    }

    private function writeGacelaFileRegisteringAGenericListener(): void
    {
        file_put_contents($this->appRoot . '/gacela.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Gacela\Framework\Bootstrap\GacelaConfig;
            use Gacela\Framework\Event\GacelaEventInterface;

            return static function (GacelaConfig $config): void {
                $config->registerGenericListener(static function (GacelaEventInterface $event): void {
                    $GLOBALS['gacela_test_events']['file'] = true;
                });
            };
            PHP);
    }
}
