<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge;

use Gacela\SymfonyBridge\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

use function dirname;
use function sprintf;

final class ConfigurationTest extends TestCase
{
    public function test_an_empty_configuration_carries_the_defaults(): void
    {
        $config = $this->process([]);

        self::assertTrue($config['enabled']);
        self::assertTrue($config['register_commands']);
        self::assertSame('gacela:', $config['command_prefix']);
        self::assertNull($config['cache_dir']);
        self::assertNull($config['file_cache']);
        self::assertSame([], $config['project_namespaces']);
        self::assertSame([], $config['external_services']);
    }

    public function test_it_reads_what_the_project_wrote(): void
    {
        $config = $this->process([[
            'app_root_dir' => '/app',
            'cache_dir' => '/app/var/gacela',
            'file_cache' => true,
            'project_namespaces' => ['App'],
            'external_services' => ['logger' => 'monolog.logger'],
            'command_prefix' => 'g:',
        ]]);

        self::assertSame('/app', $config['app_root_dir']);
        self::assertSame('/app/var/gacela', $config['cache_dir']);
        self::assertTrue($config['file_cache']);
        self::assertSame(['App'], $config['project_namespaces']);
        self::assertSame(['logger' => 'monolog.logger'], $config['external_services']);
        self::assertSame('g:', $config['command_prefix']);
    }

    /**
     * A mistyped key is a five-second fix at compile time and a mystery at the
     * first use of whatever it was supposed to configure.
     */
    public function test_an_unknown_key_fails_the_build(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['file_cach' => true]]);
    }

    public function test_a_value_of_the_wrong_type_fails_the_build(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['file_cache' => 'yes please']]);
    }

    /**
     * The tree is the list of keys a project may write, and the README is the
     * only place a project reads it from. They drifted: `enabled` was declared
     * here, acted on by `GacelaExtension::load()` -- which returns before
     * registering anything when it is false -- and documented nowhere, so the
     * supported way to keep the bundle registered while leaving Gacela
     * un-bootstrapped was undiscoverable.
     *
     * Asserted against the tree rather than a hand-written list, so the next key
     * added fails this instead of shipping undocumented.
     */
    public function test_every_configuration_key_is_documented_in_the_readme(): void
    {
        $readme = (string)file_get_contents(
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'README.md',
        );

        $children = (new Configuration())->getConfigTreeBuilder()->buildTree()->getChildren();
        self::assertNotEmpty($children, 'no keys to check would make this pass for nothing');

        foreach ($children as $name => $_node) {
            self::assertStringContainsString(
                $name . ':',
                $readme,
                sprintf('config key "%s" is declared but the README never mentions it', $name),
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }
}
