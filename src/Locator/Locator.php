<?php

declare(strict_types=1);

namespace Gacela\Locator;

final class Locator
{
    private ?ModuleProxy $moduleProxy = null;

    private ?array $locator = null;

    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public function __call(string $moduleName, array $arguments = []): ModuleProxy
    {
        if ($this->moduleProxy === null) {
            $this->moduleProxy = $this->getModuleProxy();
        }

        return $this->moduleProxy->setModuleName($moduleName);
    }

    private function getModuleProxy(): ModuleProxy
    {
        $bundleProxy = new ModuleProxy();
        if ($this->locator === null) {
            $this->locator = [
                new FacadeLocator(),
            ];
        }
        $bundleProxy->setLocators($this->locator);

        return $bundleProxy;
    }
}
