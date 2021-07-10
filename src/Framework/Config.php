<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Config\ConfigInit;
use Gacela\Framework\Config\ConfigReader\EnvConfigReader;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Config\GacelaJsonConfigFactory;
use Gacela\Framework\Config\GacelaJsonConfigFactoryInterface;
use Gacela\Framework\Config\PathFinder;
use Gacela\Framework\Config\PathFinderInterface;
use Gacela\Framework\Exception\ConfigException;

final class Config
{
    private const GACELA_CONFIG_FILENAME = 'gacela.json';

    private static string $applicationRootDir = '';

    private static ?self $instance = null;

    private array $config = [];

    /** @var array<string, ConfigReaderInterface> */
    private array $configReaders;

    /**
     * @param array<string, ConfigReaderInterface> $configReaders
     */
    private function __construct(array $configReaders)
    {
        $this->configReaders = $configReaders;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self([
                'php' => new PhpConfigReader(),
                'env' => new EnvConfigReader(),
            ]);
        }

        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * @param array<string, ConfigReaderInterface> $configReaders
     */
    public function setConfigReaders(array $configReaders = []): void
    {
        $this->configReaders = $configReaders;
    }

    public static function setApplicationRootDir(string $dir): void
    {
        self::$applicationRootDir = $dir;
    }

    public static function getApplicationRootDir(): string
    {
        if (empty(self::$applicationRootDir)) {
            self::$applicationRootDir = getcwd() ?: '';
        }

        return self::$applicationRootDir;
    }

    /**
     * @param null|mixed $default
     *
     * @throws ConfigException
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (empty($this->config)) {
            $this->init();
        }

        if ($default !== null && !$this->hasValue($key)) {
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
        $this->config = (new ConfigInit(
            self::getApplicationRootDir(),
            $this->createGacelaJsonConfigCreator(),
            $this->createPathFinder(),
            $this->configReaders
        ))->readAll();
    }

    private function createGacelaJsonConfigCreator(): GacelaJsonConfigFactoryInterface
    {
        return new GacelaJsonConfigFactory(
            self::$applicationRootDir,
            self::GACELA_CONFIG_FILENAME
        );
    }

    private function createPathFinder(): PathFinderInterface
    {
        return new PathFinder();
    }

    private function hasValue(string $key): bool
    {
        return isset($this->config[$key]);
    }
}
