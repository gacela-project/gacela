<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\SetupGacela;
use ReflectionMethod;

use function array_merge;
use function array_unique;

/**
 * Merges individual properties into a SetupGacela instance.
 */
final class PropertyMerger
{
    public function __construct(
        private readonly SetupGacela $setup,
    ) {
    }

    /**
     * @param array<string,class-string|object|callable> $list
     */
    public function combineExternalServices(array $list): void
    {
        $current = $this->setup->externalServices();
        $this->setup->setExternalServices(array_merge($current, $list));
    }

    /**
     * @param list<string> $list
     */
    public function combineProjectNamespaces(array $list): void
    {
        $current = $this->setup->getProjectNamespaces();
        $this->setup->setProjectNamespaces(array_merge($current, $list));
    }

    /**
     * @param array<string,mixed> $list
     */
    public function combineConfigKeyValues(array $list): void
    {
        $current = $this->setup->getConfigKeyValues();
        $this->setup->setConfigKeyValues(array_merge($current, $list));
    }

    /**
     * @param list<class-string> $list
     */
    public function combineGacelaConfigsToExtend(array $list): void
    {
        $current = $this->setup->getGacelaConfigsToExtend();

        // Use reflection to call private method setGacelaConfigsToExtend
        $method = new ReflectionMethod($this->setup, 'setGacelaConfigsToExtend');
        $method->setAccessible(true);
        $method->invoke($this->setup, array_unique(array_merge($current, $list)));
    }

    /**
     * @param list<class-string|callable> $list
     */
    public function combinePlugins(array $list): void
    {
        $current = $this->setup->getPlugins();

        // Use reflection to call private method setPlugins
        $method = new ReflectionMethod($this->setup, 'setPlugins');
        $method->setAccessible(true);
        $method->invoke($this->setup, array_merge($current, $list));
    }
}
