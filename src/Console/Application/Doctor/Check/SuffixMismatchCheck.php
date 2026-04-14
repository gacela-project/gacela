<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\AllAppModules\AppModule;

use function count;
use function sprintf;

final class SuffixMismatchCheck implements HealthCheck
{
    /** @var array{Facade: list<string>, Factory: list<string>, Config: list<string>, Provider: list<string>} */
    private readonly array $suffixTypes;

    /**
     * @param list<AppModule> $modules
     * @param array{Facade?: list<string>, Factory?: list<string>, Config?: list<string>, Provider?: list<string>} $suffixTypes
     */
    public function __construct(
        private readonly array $modules,
        array $suffixTypes,
    ) {
        $this->suffixTypes = [
            'Facade' => $suffixTypes['Facade'] ?? ['Facade'],
            'Factory' => $suffixTypes['Factory'] ?? ['Factory'],
            'Config' => $suffixTypes['Config'] ?? ['Config'],
            'Provider' => $suffixTypes['Provider'] ?? ['Provider'],
        ];
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

        foreach ($this->modules as $module) {
            $this->inspect('Facade', $module->facadeClass(), $this->suffixTypes['Facade'], $errors);
            $this->inspectOptional('Factory', $module->factoryClass(), $this->suffixTypes['Factory'], $warnings);
            $this->inspectOptional('Config', $module->configClass(), $this->suffixTypes['Config'], $warnings);
            $this->inspectOptional('Provider', $module->providerClass(), $this->suffixTypes['Provider'], $warnings);
        }

        if ($errors !== []) {
            return CheckResult::error(
                $this->name(),
                [...$errors, ...$warnings],
                'add the missing suffix via `SuffixTypesBuilder::addFacade/Factory/Config/Provider` in gacela.php',
            );
        }

        if ($warnings !== []) {
            return CheckResult::warn(
                $this->name(),
                $warnings,
                'configure the suffix in gacela.php or rename the file to match a configured suffix',
            );
        }

        return CheckResult::ok($this->name(), sprintf('%d module(s) use configured suffixes', count($this->modules)));
    }

    /**
     * @param list<string> $configured
     * @param list<string> $bucket
     */
    private function inspect(string $kind, string $className, array $configured, array &$bucket): void
    {
        if (!$this->endsWithAny($className, $configured)) {
            $bucket[] = sprintf(
                '%s "%s" does not end with any configured %s suffix [%s]',
                $kind,
                $className,
                $kind,
                implode(', ', $configured),
            );
        }
    }

    /**
     * @param list<string> $configured
     * @param list<string> $bucket
     */
    private function inspectOptional(string $kind, ?string $className, array $configured, array &$bucket): void
    {
        if ($className === null) {
            return;
        }

        $this->inspect($kind, $className, $configured, $bucket);
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
}
