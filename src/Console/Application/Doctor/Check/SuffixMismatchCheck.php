<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

use function array_unique;
use function array_values;
use function count;
use function sprintf;
use function strlen;
use function usort;

/**
 * Reports a discovered class whose name ends in no suffix configured for its kind.
 *
 * The map it reads is every kind the project configured, not the four pillars:
 * a declared kind owns names too, and a facade called `ReportExporter` in a
 * project that declared an `Exporter` kind is not an unsuffixed facade -- it is
 * a facade whose name another kind already answers to. The two read the same in
 * a report unless the second says who took the name.
 *
 * @psalm-import-type SuffixTypes from SuffixTypesBuilder
 */
final class SuffixMismatchCheck implements HealthCheck
{
    /** @var SuffixTypes */
    private readonly array $suffixTypes;

    /** @var list<array{kind: string, suffix: string}> */
    private readonly array $matchOrder;

    /**
     * @param list<AppModule> $modules
     * @param SuffixTypes $suffixTypes kind => suffixes; a project-declared kind is an ordinary key
     */
    public function __construct(
        private readonly array $modules,
        array $suffixTypes,
    ) {
        $this->suffixTypes = $this->onTopOfThePillars($suffixTypes);
        $this->matchOrder = $this->matchOrder($this->suffixTypes);
    }

    public function name(): string
    {
        return 'suffix configuration';
    }

    public function run(): CheckResult
    {
        if ($this->modules === []) {
            return CheckResult::ok($this->name(), 'no modules discovered');
        }

        $errors = [];
        $warnings = [];

        // Only the kinds a module carries a slot for. A declared kind is in the
        // map and names the classes it owns, but module discovery has nowhere to
        // put one, so there is no such class here to inspect -- and nothing to
        // report about it either.
        foreach ($this->modules as $module) {
            $this->inspect('Facade', $module->facadeClass(), $errors);
            $this->inspectOptional('Factory', $module->factoryClass(), $warnings);
            $this->inspectOptional('Config', $module->configClass(), $warnings);
            $this->inspectOptional('Provider', $module->providerClass(), $warnings);
        }

        if ($errors !== []) {
            return CheckResult::error(
                $this->name(),
                [...$errors, ...$warnings],
                'add the missing suffix in gacela.php with `GacelaConfig::addSuffixTypeFacade()`'
                . ' -- or its Factory, Config and Provider siblings',
            );
        }

        if ($warnings !== []) {
            return CheckResult::warn(
                $this->name(),
                $warnings,
                'configure the suffix in gacela.php with `GacelaConfig::addSuffixTypeFactory()`'
                . ' (or its Config and Provider siblings), or rename the file to match a configured suffix',
            );
        }

        return CheckResult::ok($this->name(), sprintf('%d module(s) use configured suffixes', count($this->modules)));
    }

    /**
     * @param list<string> $bucket
     */
    private function inspect(string $kind, string $className, array &$bucket): void
    {
        $configured = $this->suffixesOf($kind);

        if ($this->endsWithAny($className, $configured)) {
            return;
        }

        $bucket[] = sprintf(
            '%s "%s" does not end with any configured %s suffix [%s]%s',
            $kind,
            $className,
            $kind,
            implode(', ', $configured),
            $this->claimedBy($className, $kind),
        );
    }

    /**
     * @param list<string> $bucket
     */
    private function inspectOptional(string $kind, ?string $className, array &$bucket): void
    {
        if ($className === null) {
            return;
        }

        $this->inspect($kind, $className, $bucket);
    }

    /**
     * Which other kind the name already belongs to, when one does.
     *
     * A name ending in no configured suffix at all is simply unsuffixed. A name
     * ending in *another* kind's suffix does resolve -- as that kind -- which is
     * the harder failure to see and the one worth naming.
     */
    private function claimedBy(string $className, string $kind): string
    {
        foreach ($this->matchOrder as ['kind' => $owner, 'suffix' => $suffix]) {
            if ($owner !== $kind && str_ends_with($className, $suffix)) {
                return sprintf('; "%s" is configured for the "%s" type', $suffix, $owner);
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function suffixesOf(string $kind): array
    {
        return $this->suffixTypes[$kind] ?? [$kind];
    }

    /**
     * @param list<string> $suffixes
     */
    private function endsWithAny(string $className, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            if (str_ends_with($className, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The configured map, read on top of the four pillars rather than instead
     * of them: `SuffixTypesBuilder` seeds the pillars and can only widen, so a
     * map naming one kind still has to be read as naming the other four too.
     *
     * @param SuffixTypes $suffixTypes
     *
     * @return SuffixTypes
     */
    private function onTopOfThePillars(array $suffixTypes): array
    {
        $merged = SuffixTypesBuilder::DEFAULT_SUFFIX_TYPES;

        foreach ($suffixTypes as $kind => $suffixes) {
            $merged[$kind] = array_values(array_unique([...$merged[$kind] ?? [], ...$suffixes]));
        }

        return $merged;
    }

    /**
     * Every (kind, suffix) pair, longest suffix first and ambiguous ones
     * dropped -- the order the resolver matches a class name in, so the kind
     * reported here is the kind that would really answer to the name.
     *
     * @param SuffixTypes $suffixTypes
     *
     * @return list<array{kind: string, suffix: string}>
     */
    private function matchOrder(array $suffixTypes): array
    {
        $owners = [];
        foreach ($suffixTypes as $kind => $suffixes) {
            foreach ($suffixes as $suffix) {
                $owners[$suffix][$kind] = true;
            }
        }

        $pairs = [];
        foreach ($suffixTypes as $kind => $suffixes) {
            foreach ($suffixes as $suffix) {
                // A suffix two kinds share names neither of them.
                if (count($owners[$suffix]) === 1) {
                    $pairs[] = ['kind' => $kind, 'suffix' => $suffix];
                }
            }
        }

        usort($pairs, $this->byLongestSuffix(...));

        return $pairs;
    }

    /**
     * @param array{kind: string, suffix: string} $a
     * @param array{kind: string, suffix: string} $b
     */
    private function byLongestSuffix(array $a, array $b): int
    {
        return strlen($b['suffix']) <=> strlen($a['suffix']);
    }
}
