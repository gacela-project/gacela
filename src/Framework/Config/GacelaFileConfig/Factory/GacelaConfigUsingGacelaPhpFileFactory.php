<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Config\FileIoInterface;
use Gacela\Framework\Config\GacelaConfigFileFactoryInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use RuntimeException;

use function get_debug_type;
use function is_callable;
use function sprintf;

/**
 * The one reader of a file that returns a `callable(GacelaConfig)`.
 *
 * Three kinds of file have that shape -- a project's `gacela.php`, its
 * `gacela-{APP_ENV}.php`, and the config an installed package declares in
 * `extra.gacela.config` -- and they are read here, once each, so the shape has
 * one definition.
 *
 * `createGacelaFileConfig()` merges into the bootstrap setup, so it is called
 * once per instance. One instance per file.
 */
final class GacelaConfigUsingGacelaPhpFileFactory implements GacelaConfigFileFactoryInterface
{
    private bool $wasRead = false;

    private ?SetupGacela $setup = null;

    /**
     * What the file returned, when it was not a callable -- kept only to name it
     * in the message below.
     */
    private mixed $returned = null;

    public function __construct(
        private readonly string $gacelaPhpPath,
        private readonly SetupGacelaInterface $bootstrapSetup,
        private readonly FileIoInterface $fileIo,
    ) {
    }

    /**
     * The setup this file declares, or null when the file does not return a
     * `callable(GacelaConfig)`.
     *
     * Separate from `createGacelaFileConfig()`, and memoized, because package
     * discovery has to know what the project opted out of before it runs any
     * package's code -- and `dontDiscover()` is written in the very file this
     * reads. Answering that by reading the file a second time would run a
     * project's configuration twice, which is the fault this whole layer already
     * documents at length.
     *
     * Null rather than an exception, because the two callers want opposite
     * things from a file that returns nothing usable. For a project's own file
     * it is fatal -- nothing works without it, and `createGacelaFileConfig()`
     * below says so. For a package's it is not: installing a package must never
     * be able to stop an application from booting, so discovery records the
     * broken declaration and `doctor` reports it.
     */
    public function createSetup(): ?SetupGacela
    {
        if (!$this->wasRead) {
            $this->wasRead = true;

            $gacelaConfig = $this->createGacelaConfig();

            $this->setup = $gacelaConfig instanceof GacelaConfig
                ? SetupGacela::fromGacelaConfig($gacelaConfig)
                : null;
        }

        return $this->setup;
    }

    public function createGacelaFileConfig(): GacelaConfigFileInterface
    {
        $projectSetupGacela = $this->createSetup();

        if (!$projectSetupGacela instanceof SetupGacela) {
            // Named, because this factory reads `gacela-{APP_ENV}.php` as well:
            // saying "gacela.php" sent you to the file that was fine, in the one
            // environment where the other one was read. What came back instead
            // is named too -- `include` yields 1 for a file that returns
            // nothing, which is unrecognisable otherwise.
            throw new RuntimeException(sprintf(
                'The file "%s" must return a `callable(GacelaConfig)`, it returned %s.',
                $this->gacelaPhpPath,
                get_debug_type($this->returned),
            ));
        }

        $this->bootstrapSetup->merge($projectSetupGacela);

        return GacelaConfigFileAssembler::assemble(
            $projectSetupGacela,
            $this->bootstrapSetup->externalServices(),
        );
    }

    private function createGacelaConfig(): ?GacelaConfig
    {
        $gacelaConfig = new GacelaConfig($this->bootstrapSetup->externalServices());

        /** @var callable(GacelaConfig):void|null $configFn */
        $configFn = $this->fileIo->include($this->gacelaPhpPath);

        if (!is_callable($configFn)) {
            $this->returned = $configFn;

            return null;
        }

        $configFn($gacelaConfig);

        return $gacelaConfig;
    }
}
