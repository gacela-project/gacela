<?php

declare(strict_types=1);

namespace Gacela\Locator;

final class Locator
{
    private ?ModuleProxy $moduleProxy = null;

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
            $this->moduleProxy = $this->prepareModuleProxy();
        }

        return $this->moduleProxy->setModuleName($moduleName);
    }

    private function prepareModuleProxy(): ModuleProxy
    {
        $moduleProxy = new ModuleProxy();
        $moduleProxy->addLocator(new FacadeLocator());

        return $moduleProxy;
    }
}
