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

final class GacelaConfigUsingGacelaPhpFileFactory implements GacelaConfigFileFactoryInterface
{
    public function __construct(
        private readonly string $gacelaPhpPath,
        private readonly SetupGacelaInterface $bootstrapSetup,
        private readonly FileIoInterface $fileIo,
    ) {
    }

    public function createGacelaFileConfig(): GacelaConfigFileInterface
    {
        $projectGacelaConfig = $this->createGacelaConfig();
        $projectSetupGacela = SetupGacela::fromGacelaConfig($projectGacelaConfig);

        $this->bootstrapSetup->merge($projectSetupGacela);

        return GacelaConfigFileAssembler::assemble(
            $projectSetupGacela,
            $this->bootstrapSetup->externalServices(),
        );
    }

    private function createGacelaConfig(): GacelaConfig
    {
        $gacelaConfig = new GacelaConfig($this->bootstrapSetup->externalServices());

        /** @var callable(GacelaConfig):void|null $configFn */
        $configFn = $this->fileIo->include($this->gacelaPhpPath);

        if (!is_callable($configFn)) {
            // Named, because this factory reads `gacela-{APP_ENV}.php` as well:
            // saying "gacela.php" sent you to the file that was fine, in the one
            // environment where the other one was read. What came back instead
            // is named too -- `include` yields 1 for a file that returns
            // nothing, which is unrecognisable otherwise.
            throw new RuntimeException(sprintf(
                'The file "%s" must return a `callable(GacelaConfig)`, it returned %s.',
                $this->gacelaPhpPath,
                get_debug_type($configFn),
            ));
        }

        $configFn($gacelaConfig);

        return $gacelaConfig;
    }
}
