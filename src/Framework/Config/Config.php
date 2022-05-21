<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Exception\ConfigException;

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
     * @internal for testing purposes
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
                $this->setup ?? new SetupGacela()
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
        return $this->getFactory()
            ->createConfigLoader()
            ->loadAll();
    }
}
