<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Package;

use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

use function array_filter;
use function array_keys;
use function array_map;
use function count;
use function is_string;
use function sprintf;

/**
 * What one package's configuration declared, read off that package's own setup
 * before it was merged into anything.
 *
 * Taken from the package's setup rather than reconstructed by diffing the merged
 * configuration: a binding two packages both declare is one entry in the merged
 * map, and a diff would credit it to whichever ran last.
 *
 * A fixed set of accessors would be a dozen methods and a dozen renderers, so
 * this is a map of kind to labels. The kinds are the ones a package can
 * meaningfully ship; `addAppConfig()` is deliberately not among them, because a
 * config path resolves against the *application* root and a package cannot know
 * what is there.
 */
final class PackageContribution
{
    /**
     * @param array<string, list<string>> $items kind => what it declared, in declaration order
     */
    private function __construct(
        private readonly array $items,
    ) {
    }

    public static function of(SetupGacela $setup, GacelaConfigFileInterface $configFile): self
    {
        $items = [
            'bindings' => self::stringKeys($configFile->getBindings()),
            'resolvable kinds' => array_keys(array_filter($configFile->getSuffixTypes(), static fn (array $suffixes): bool => $suffixes !== [])),
            'plugins' => array_map(self::label(), $setup->getPlugins()),
            'plugin stacks' => self::pluginStacks($setup),
            'listeners' => self::listeners($setup),
            'factories' => self::stringKeys($setup->getFactories()),
            'protected services' => self::stringKeys($setup->getProtectedServices()),
            'lazy services' => self::stringKeys($setup->getLazyServices()),
            'aliases' => self::stringKeys($setup->getAliases()),
            'handler registries' => self::stringKeys($setup->getHandlerRegistries()),
            'tags' => self::stringKeys($setup->getTags()),
            'config keys' => self::stringKeys($setup->getConfigKeyValues()),
            'config schema' => self::stringKeys($setup->getConfigSchema()),
            'dto schema' => self::stringKeys($setup->getDtoSchema()),
        ];

        return new self(array_filter($items, static fn (array $labels): bool => $labels !== []));
    }

    /**
     * @param array<string, list<string>> $items
     */
    public static function fromArray(array $items): self
    {
        return new self($items);
    }

    /**
     * @return array<string, list<string>>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * A package whose config file declares nothing. Worth saying out loud
     * rather than rendering as a blank: it is either a mistake or a package that
     * only registers things through a plugin at runtime.
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * One line, for a listing that has no room for the labels.
     */
    public function summary(): string
    {
        if ($this->items === []) {
            return 'nothing';
        }

        $parts = [];

        foreach ($this->items as $kind => $labels) {
            $parts[] = sprintf('%d %s', count($labels), $kind);
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<array-key, mixed> $map
     *
     * @return list<string>
     */
    private static function stringKeys(array $map): array
    {
        $keys = [];

        foreach (array_keys($map) as $key) {
            $keys[] = is_string($key) ? $key : (string) $key;
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private static function pluginStacks(SetupGacela $setup): array
    {
        $labels = [];

        foreach ($setup->getPluginStacks() as $contract => $plugins) {
            foreach ($plugins as $plugin) {
                $labels[] = sprintf('%s => %s', $contract, self::labelOf($plugin));
            }
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    private static function listeners(SetupGacela $setup): array
    {
        $labels = [];

        foreach ($setup->getSpecificListeners() ?? [] as $event => $listeners) {
            $labels[] = sprintf('%s (%d)', $event, count($listeners));
        }

        $generic = count($setup->getGenericListeners() ?? []);

        if ($generic > 0) {
            $labels[] = sprintf('every event (%d)', $generic);
        }

        return $labels;
    }

    /**
     * @return callable(mixed):string
     */
    private static function label(): callable
    {
        return self::labelOf(...);
    }

    /**
     * A plugin is a class-string or a callable, and a callable has no name to
     * print.
     */
    private static function labelOf(mixed $value): string
    {
        return is_string($value) ? $value : 'closure';
    }
}
