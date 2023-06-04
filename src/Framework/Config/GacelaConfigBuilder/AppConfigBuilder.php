<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaConfigBuilder;

use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;

use function is_string;

final class AppConfigBuilder
{
    /** @var list<GacelaConfigItem> */
    private array $configItems = [];

    /**
     * @param string $path define the path where Gacela will read all the config files
     * @param string $pathLocal define the path where Gacela will read the local config file
     * @param class-string<ConfigReaderInterface>|ConfigReaderInterface|null $reader Define the reader class which will read and parse the config files
     */
    public function add(string $path, string $pathLocal = '', string|ConfigReaderInterface $reader = null): self
    {
        $readerInstance = $this->normalizeReader($reader);

        $this->configItems[] = new GacelaConfigItem($path, $pathLocal, $readerInstance);

        return $this;
    }

    /**
     * @return list<GacelaConfigItem>
     */
    public function build(): array
    {
        return $this->configItems;
    }

    /**
     * @param class-string<ConfigReaderInterface>|ConfigReaderInterface|null $reader
     */
    private function normalizeReader(string|ConfigReaderInterface|null $reader): ConfigReaderInterface
    {
        if ($reader instanceof ConfigReaderInterface) {
            return $reader;
        }

        if (is_string($reader)) {
            return new $reader();
        }

        return new PhpConfigReader();
    }
}
