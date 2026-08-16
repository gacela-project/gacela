<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp;

use Gacela\Console\Infrastructure\Command\CommandCatalog;
use Gacela\Framework\Attribute\Cacheable;
use Gacela\Framework\Attribute\CacheableTrait;
use Gacela\Framework\Attribute\Inject;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ConfigResolverAwareTrait;
use Gacela\Framework\DeclaredTypeResolverAwareTrait;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

use function file_get_contents;
use function implode;
use function sprintf;
use function str_contains;
use function strrpos;
use function substr;

/**
 * What keeps the reference application a reference.
 *
 * An application that uses every feature is only useful while it still does. A
 * new `GacelaConfig` method, a new attribute or a new command lands here as a
 * failing test naming itself, and the fix is either a place in the application
 * where the new thing belongs -- which is the design review the feature wanted
 * anyway -- or a line in the allow-list saying why it has none.
 *
 * The allow-list is deliberately small and each entry carries its reason. An
 * entry with no reason is a feature nobody has looked at.
 */
final class ReferenceAppUsesEveryFeatureTest extends TestCase
{
    /**
     * `GacelaConfig` methods the application does not call, and why.
     *
     * @var array<string, string>
     */
    private const ALLOWED_CONFIG_METHODS = [
        'disableEventListeners' => 'switches off the dispatcher, which would silence the listeners this application registers to demonstrate them; the two cannot both be shown in one app',
        'toTransfer' => 'marked @internal: the bootstrap hands the assembled configuration to the framework, and no application calls it',
        'defaultPhpConfig' => "a shorthand for addAppConfig('config/*.php', 'config/local.php'), which gacela.php writes out so the reader can see what it does",
    ];

    /**
     * Traits a module can `use`, and why one of them is not written anywhere.
     *
     * @var array<string, string>
     */
    private const ALLOWED_TRAITS = [
        CacheableTrait::class => 'AbstractFacade already uses it, so a facade with #[Cacheable] needs no `use` of its own -- the attribute and $this->cached() are what an application writes',
    ];

    /** The application, its harness, and the configuration that drives both. */
    private const SCANNED_DIRECTORY = __DIR__;

    public function test_the_application_calls_every_public_gacela_config_method(): void
    {
        $source = $this->scannedSource();
        $missing = [];

        foreach ($this->publicConfigMethods() as $method) {
            if (isset(self::ALLOWED_CONFIG_METHODS[$method])) {
                continue;
            }

            if (!str_contains($source, '$config->' . $method . '(')) {
                $missing[] = $method;
            }
        }

        self::assertSame([], $missing, sprintf(
            "The reference application does not use: %s.\n"
            . "Call it on the \$config in gacela.php, gacela-prod.php or a bootstrap closure where it belongs,\n"
            . 'or add it to ALLOWED_CONFIG_METHODS with the reason it has no natural home.',
            implode(', ', array_map(static fn (string $m): string => 'GacelaConfig::' . $m . '()', $missing)),
        ));
    }

    /**
     * Self-invalidating, like every allow-list in this repository: an entry for
     * a method that no longer exists reads as a decision still being honoured.
     */
    public function test_every_allowed_config_method_still_exists(): void
    {
        $existing = $this->publicConfigMethods();

        foreach (array_keys(self::ALLOWED_CONFIG_METHODS) as $allowed) {
            self::assertContains($allowed, $existing, sprintf(
                'GacelaConfig::%s() is allow-listed and no longer exists. Remove the entry.',
                $allowed,
            ));
        }
    }

    public function test_the_application_uses_every_attribute_gacela_ships(): void
    {
        $source = $this->scannedSource();
        $missing = [];

        foreach ([Cacheable::class, Inject::class, Provides::class, ServiceMap::class] as $attribute) {
            $shortName = $this->shortNameOf($attribute);

            if (!str_contains($source, '#[' . $shortName)) {
                $missing[] = $attribute;
            }
        }

        self::assertSame([], $missing, sprintf(
            'The reference application declares none of: %s. Put each on the class or method it belongs to.',
            implode(', ', $missing),
        ));
    }

    public function test_the_application_uses_every_module_trait(): void
    {
        $source = $this->scannedSource();
        $missing = [];

        $traits = [
            ServiceResolverAwareTrait::class,
            ConfigResolverAwareTrait::class,
            DeclaredTypeResolverAwareTrait::class,
            CacheableTrait::class,
        ];

        foreach ($traits as $trait) {
            if (isset(self::ALLOWED_TRAITS[$trait])) {
                continue;
            }

            if (!str_contains($source, 'use ' . $this->shortNameOf($trait) . ';')) {
                $missing[] = $trait;
            }
        }

        self::assertSame([], $missing, sprintf(
            "The reference application uses none of: %s.\n"
            . 'Add the `use` to the pillar that needs it, or add the trait to ALLOWED_TRAITS with a reason.',
            implode(', ', $missing),
        ));
    }

    public function test_every_allowed_trait_still_exists(): void
    {
        foreach (array_keys(self::ALLOWED_TRAITS) as $trait) {
            self::assertTrue(trait_exists($trait), sprintf(
                '%s is allow-listed and no longer exists. Remove the entry.',
                $trait,
            ));
        }
    }

    /**
     * Every command in the catalogue is run by `InvoicingToolingTest`, so a new
     * command arrives here as a failing test rather than as a command nobody
     * ever pointed at an application.
     */
    public function test_the_harness_runs_every_command_gacela_ships(): void
    {
        $source = $this->scannedSource();
        $missing = [];

        foreach (CommandCatalog::classes() as $commandClass) {
            if (!str_contains($source, 'new ' . $this->shortNameOf($commandClass) . '(')) {
                $missing[] = $commandClass;
            }
        }

        self::assertSame([], $missing, sprintf(
            "InvoicingToolingTest does not run: %s.\n"
            . 'Add a test that runs it against the reference application and asserts its exit code and one fact of its output.',
            implode(', ', $missing),
        ));
    }

    /**
     * The scan is worth nothing if it is reading nothing.
     */
    public function test_the_scan_reads_the_application(): void
    {
        $source = $this->scannedSource();

        self::assertStringContainsString('$config->addAppConfig(', $source, 'gacela.php was not scanned');
        self::assertStringContainsString('final class BillingFacade', $source, 'the modules were not scanned');
        self::assertStringContainsString('new DoctorCommand(', $source, 'the harness was not scanned');
    }

    /**
     * @return list<string>
     */
    private function publicConfigMethods(): array
    {
        $methods = [];

        foreach ((new ReflectionClass(GacelaConfig::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor()) {
                continue;
            }

            $methods[] = $method->getName();
        }

        return $methods;
    }

    /**
     * Every `.php` file of the application and its harness, concatenated --
     * except this file, whose allow-list names the very methods it is looking
     * for and would answer its own question.
     */
    private function scannedSource(): string
    {
        static $source = null;

        if ($source !== null) {
            return $source;
        }

        $parts = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            self::SCANNED_DIRECTORY,
            RecursiveDirectoryIterator::SKIP_DOTS,
        ));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if ($file->getFilename() === basename(__FILE__)) {
                continue;
            }

            $parts[] = (string)file_get_contents($file->getPathname());
        }

        return $source = implode("\n", $parts);
    }

    private function shortNameOf(string $className): string
    {
        $position = strrpos($className, '\\');

        return $position === false ? $className : substr($className, $position + 1);
    }
}
