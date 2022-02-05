<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Config\ConfigFactory;
use Gacela\Framework\Config\ConfigLoader;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Exception\ConfigException;

final class Config
{
    public const DEFAULT_CONFIG_VALUE = 'Gacela\Framework\Config::DEFAULT_CONFIG_VALUE';

    private static ?self $instance = null;

    private string $appRootDir = '';

    /** @var array<string,mixed> */
    private array $config = [];

    /** @var array<string,ConfigReaderInterface> */
    private array $configReaders;

    /** @var array<string,mixed> */
    private array $globalConfigServices = [];

    private ?ConfigFactory $configFactory = null;

    private function __construct()
    {
        $this->setConfigReaders([]);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param array<string,ConfigReaderInterface> $configReaders
     */
    public function setConfigReaders(array $configReaders = []): self
    {
        if (empty($configReaders)) {
            $configReaders = ['php' => new PhpConfigReader()];
        }

        $this->configReaders = $configReaders;

        return $this;
    }

    /**
     * @param null|mixed $default
     *
     * @throws ConfigException
     *
     * @return mixed
     */
    public function get(string $key, $default = self::DEFAULT_CONFIG_VALUE)
    {
        if (empty($this->config)) {
            $this->init();
        }

        if ($default !== self::DEFAULT_CONFIG_VALUE && !$this->hasValue($key)) {
            return $default;
        }

        if (!$this->hasValue($key)) {
            throw ConfigException::keyNotFound($key, self::class);
        }

        return $this->config[$key];
    }

    /**
     * @throws ConfigException
     */
    public function init(): void
    {
        $this->config = $this->loadAllConfigValues();
    }

    public function setAppRootDir(string $dir): self
    {
        $this->appRootDir = $dir;

        if (empty($this->appRootDir)) {
            $this->appRootDir = getcwd() ?: '';
        }

        return $this;
    }

    public function getAppRootDir(): string
    {
        return $this->appRootDir;
    }

    /**
     * @deprecated Use `setAppRootDir(string $dir)` instead
     */
    public function setApplicationRootDir(string $dir): void
    {
        $this->setAppRootDir($dir);
    }

    /**
     * @deprecated use `getAppRootDir()` instead
     */
    public function getApplicationRootDir(): string
    {
        return $this->getAppRootDir();
    }

    /**
     * @param array<string,mixed> $globalConfigServices
     */
    public function setGlobalConfigServices(array $globalConfigServices): self
    {
        $this->configFactory = null;
        $this->globalConfigServices = $globalConfigServices;

        return $this;
    }

    /**
     * @internal
     */
    public function getFactory(): ConfigFactory
    {
        if (null === $this->configFactory) {
            $this->configFactory = new ConfigFactory(
                $this->getAppRootDir(),
                $this->globalConfigServices
            );
        }

        return $this->configFactory;
    }

    private function hasValue(string $key): bool
    {
        return isset($this->config[$key]);
    }

    /**
     * @return array<string,mixed>
     */
    private function loadAllConfigValues(): array
    {
        $configLoader = new ConfigLoader(
            $this->getAppRootDir(),
            $this->getFactory()->createGacelaConfigFileFactory(),
            $this->getFactory()->createPathFinder(),
            $this->configReaders
        );

        return $configLoader->loadAll();
    }
}
