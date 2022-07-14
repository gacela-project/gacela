<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Exception\ConfigException;

use function array_key_exists;

final class Config
{
    public const DEFAULT_CONFIG_VALUE = 'Gacela\Framework\Config::DEFAULT_CONFIG_VALUE';

    private static ?self $instance = null;

    private ?string $appRootDir = null;

    /** @var array<string,mixed> */
    private array $config = [];

    private ?SetupGacelaInterface $setup = null;

    private ?ConfigFactory $configFactory = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @internal
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
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
     * Force loading all config values in memory.
     *
     * @throws ConfigException
     */
    public function init(): void
    {
        $this->configFactory = null;
        $this->config = $this->loadAllConfigValues();
        $this->config = array_merge($this->config, $this->getSetupGacela()->getConfigKeyValues());
    }

    public function setAppRootDir(string $dir): self
    {
        $this->appRootDir = $dir;

        if (empty($this->appRootDir)) {
            $this->appRootDir = getcwd() ?: ''; // @codeCoverageIgnore
        }

        return $this;
    }

    public function getAppRootDir(): string
    {
        return $this->appRootDir ?? getcwd() ?: '';
    }

    public function getCacheDir(): string
    {
        return $this->getAppRootDir() . '/' . $this->getSetupGacela()->getCacheDirectory();
    }

    public function setSetup(SetupGacelaInterface $setup): self
    {
        $this->setup = $setup;

        return $this;
    }

    /**
     * @internal
     */
    public function getFactory(): ConfigFactory
    {
        if ($this->configFactory === null) {
            $this->configFactory = new ConfigFactory(
                $this->getAppRootDir(),
                $this->getSetupGacela()
            );
        }

        return $this->configFactory;
    }

    public function getSetupGacela(): SetupGacelaInterface
    {
        if ($this->setup === null) {
            $this->setup = new SetupGacela();
        }

        return $this->setup;
    }

    private function hasValue(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * @return array<string,mixed>
     */
    private function loadAllConfigValues(): array
    {
        return $this->getFactory()
            ->createConfigLoader()
            ->loadAll();
    }
}
