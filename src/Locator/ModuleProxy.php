<?php

declare(strict_types=1);

namespace Gacela\Locator;

use LogicException;

final class ModuleProxy
{
    private const LOCATOR_MATCHER_SUFFIX = 'Matcher';

    private string $moduleName = '';

    /** @var LocatorInterface[] */
    private array $locators = [];

    /** @var array<string,LocatorMatcherInterface> */
    private array $locatorMatcherMap = [];

    /** @var array<string,LocatorInterface> */
    private array $locatorMatcherByMethodNameMap = [];

    private static array $instanceCache = [];

    public function setModuleName(string $moduleName): self
    {
        $this->moduleName = $moduleName;

        return $this;
    }

    /**
     * @throws \LogicException
     */
    public function addLocator(LocatorInterface $locator): self
    {
        $locatorClass = get_class($locator);
        $matcherClass = $locatorClass . static::LOCATOR_MATCHER_SUFFIX;
        if (!class_exists($matcherClass)) {
            throw new LogicException(sprintf('Could not find a "%s"!', $matcherClass));
        }
        /** @var LocatorMatcherInterface $matcher */
        $matcher = new $matcherClass();

        $this->locators[] = $locator;
        $this->locatorMatcherMap[$locatorClass] = $matcher;

        return $this;
    }

    /**
     * @return object
     */
    public function __call(string $methodName, array $arguments)
    {
        $cacheKey = $this->buildCacheKey($methodName);

        if (isset(static::$instanceCache[$cacheKey])) {
            return static::$instanceCache[$cacheKey];
        }

        $locator = $this->getLocator($methodName);
        $located = $locator->locate(ucfirst($this->moduleName));

        static::$instanceCache[$cacheKey] = $located;

        return $located;
    }

    /**
     * @throws \LogicException
     */
    private function getLocator(string $methodName): LocatorInterface
    {
        if (isset($this->locatorMatcherByMethodNameMap[$methodName])) {
            return $this->locatorMatcherByMethodNameMap[$methodName];
        }
        foreach ($this->locators as $locator) {
            $matcher = $this->locatorMatcherMap[get_class($locator)];
            if ($matcher->match($methodName)) {
                $this->locatorMatcherByMethodNameMap[$methodName] = $locator;

                return $locator;
            }
        }

        throw new LogicException(sprintf('Could not map method "%s" to a locator!', $methodName));
    }

    private function buildCacheKey(string $methodName): string
    {
        return $this->moduleName . '-' . $methodName;
    }
}
