<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge;

use Gacela\SymfonyBridge\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

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
     * @param list<array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }
}
