<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

interface GacelaConfigFileInterface
{
    /**
     * @return list<GacelaConfigItem>
     */
    public function getConfigItems(): array;

    /**
     * Map interfaces to concrete classes or callable (which will be resolved on runtime).
     * This is util to inject dependencies to Gacela services (such as Factories, for example) via their constructor.
     *
     * @return mixed
     */
    public function getMappingInterface(string $key);


    /**
     * @return array{
     *     Factory?:list<string>,
     *     Config?:list<string>,
     *     DependencyProvider?:list<string>
     * }
     */
    public function getSuffixTypes(): array;
}
