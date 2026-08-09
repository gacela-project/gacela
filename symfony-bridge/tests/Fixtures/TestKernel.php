<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge\Fixtures;

use Gacela\SymfonyBridge\GacelaBundle;
use Override;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

use Symfony\Component\HttpKernel\Kernel;

use function bin2hex;
use function dirname;
use function random_bytes;
use function sys_get_temp_dir;

/**
 * The smallest kernel that can register a bundle: no FrameworkBundle, so what
 * a test observes is the bridge and nothing else.
 */
final class TestKernel extends Kernel
{
    private readonly string $id;

    /**
     * @param array<string, mixed>       $gacelaConfig     what `gacela.yaml` would say
     * @param array<string, class-string> $extraServices   service id => class, registered public
     */
    public function __construct(
        private readonly array $gacelaConfig = [],
        private readonly array $extraServices = [],
        string $environment = 'test',
    ) {
        $this->id = bin2hex(random_bytes(6));

        parent::__construct($environment, true);
    }

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        return [new GacelaBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('gacela', $this->gacelaConfig);

            foreach ($this->extraServices as $id => $class) {
                $definition = new Definition($class);
                $definition->setArguments([CountingService::FROM_SYMFONY]);
                $definition->setPublic(true);
                $container->setDefinition($id, $definition);
            }
        });
    }

    #[Override]
    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/gacela-bridge-kernel/' . $this->id . '/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/gacela-bridge-kernel/' . $this->id . '/log';
    }
}
