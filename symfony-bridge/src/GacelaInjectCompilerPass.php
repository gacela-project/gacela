<?php

declare(strict_types=1);

namespace Gacela\SymfonyBridge;

use Gacela\Container\Attribute\Inject;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function class_exists;
use function sprintf;

/**
 * Rewrites Symfony service definitions so constructor parameters annotated
 * with Gacela's {@see Inject} attribute resolve through Gacela's container
 * instead of Symfony's autowire.
 *
 * The consumer must register a Symfony service (default id `gacela.container`)
 * exposing a `get(string $className): object` method. Conflicts — Symfony
 * already claiming a slot that `#[Inject]` wants — fail the build.
 */
final class GacelaInjectCompilerPass implements CompilerPassInterface
{
    public function __construct(
        private readonly string $gacelaServiceId = 'gacela.container',
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isAbstract() || $definition->isSynthetic()) {
                continue;
            }

            /** @var class-string|null $class */
            $class = $definition->getClass();
            if ($class === null || !class_exists($class)) {
                continue;
            }

            $constructor = (new ReflectionClass($class))->getConstructor();
            if ($constructor === null) {
                continue;
            }

            foreach ($constructor->getParameters() as $parameter) {
                $this->rewriteIfInjected($id, $definition, $parameter);
            }
        }
    }

    private function rewriteIfInjected(string $id, Definition $definition, ReflectionParameter $parameter): void
    {
        $attributes = $parameter->getAttributes(Inject::class);
        if ($attributes === []) {
            return;
        }

        $target = $this->targetFor($attributes[0]->newInstance(), $parameter);
        if ($target === null) {
            return;
        }

        $name = $parameter->getName();
        $args = $definition->getArguments();
        if (array_key_exists('$' . $name, $args) || array_key_exists($parameter->getPosition(), $args)) {
            throw new RuntimeException(sprintf(
                'Gacela #[Inject] conflicts with an existing Symfony argument on service "%s" parameter "$%s". '
                . 'Remove the Symfony argument or drop the #[Inject] attribute.',
                $id,
                $name,
            ));
        }

        $definition->setArgument('$' . $name, (new Definition($target))
            ->setFactory([new Reference($this->gacelaServiceId), 'get'])
            ->setArguments([$target])
            ->setPublic(false));
    }

    /**
     * @return class-string|null
     */
    private function targetFor(Inject $inject, ReflectionParameter $parameter): ?string
    {
        /** @var class-string|null $override */
        $override = $inject->implementation;
        if ($override !== null) {
            return $override;
        }

        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        /** @var class-string $name */
        $name = $type->getName();
        return $name;
    }
}
