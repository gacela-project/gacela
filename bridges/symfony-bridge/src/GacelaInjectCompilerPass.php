<?php

declare(strict_types=1);

namespace Gacela\SymfonyBridge;

use Closure;
use Gacela\Container\Attribute\Inject;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Symfony\Component\DependencyInjection\ChildDefinition;
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
 * The rewritten arguments resolve through a Symfony service (default id
 * `gacela.container`) exposing a `get(string $className): object` method.
 * {@see GacelaBundle} registers it; without the bundle, the consumer must.
 * Conflicts — Symfony
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
            if ($definition->isAbstract()) {
                continue;
            }

            if ($definition->isSynthetic()) {
                continue;
            }

            // Built by a factory, the class constructor is not what Symfony
            // calls, and its arguments are the factory's.
            if ($this->inherited($container, $definition, static fn (Definition $d): string|array|null => $d->getFactory()) !== null) {
                continue;
            }

            /** @var class-string|null $class */
            $class = $this->inherited($container, $definition, static fn (Definition $d): ?string => $d->getClass());
            if ($class === null) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            $constructor = (new ReflectionClass($class))->getConstructor();
            if ($constructor === null) {
                continue;
            }

            foreach ($constructor->getParameters() as $parameter) {
                $this->rewriteIfInjected($container, $id, $definition, $parameter);
            }
        }
    }

    /**
     * A `parent:` service has no class or factory of its own until Symfony
     * resolves it, which happens after this pass, so they are read from the
     * nearest parent that sets them.
     *
     * @template T
     *
     * @param Closure(Definition): (T|null) $read
     *
     * @return T|null
     */
    private function inherited(ContainerBuilder $container, Definition $definition, Closure $read): mixed
    {
        $value = $read($definition);

        while ($value === null && $definition instanceof ChildDefinition && $container->hasDefinition($definition->getParent())) {
            $definition = $container->getDefinition($definition->getParent());
            $value = $read($definition);
        }

        return $value;
    }

    private function rewriteIfInjected(ContainerBuilder $container, string $id, Definition $definition, ReflectionParameter $parameter): void
    {
        // IS_INSTANCEOF, so a subclass re-presenting the attribute under another
        // namespace is honoured too, as `Gacela\Framework\Attribute\Inject` does.
        // An exact match would drop it silently and let Symfony autowire instead.
        $attributes = $parameter->getAttributes(Inject::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($attributes === []) {
            return;
        }

        $target = $this->targetFor($attributes[0]->newInstance(), $parameter);
        if ($target === null) {
            return;
        }

        $name = $parameter->getName();
        if ($this->claimsArgument($container, $definition, $parameter)) {
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
     * Whether Symfony already has an argument for the parameter, on the service
     * or a parent it inherits arguments from.
     */
    private function claimsArgument(ContainerBuilder $container, Definition $definition, ReflectionParameter $parameter): bool
    {
        $keys = ['$' . $parameter->getName(), $parameter->getPosition(), 'index_' . $parameter->getPosition()];

        while (true) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $definition->getArguments())) {
                    return true;
                }
            }

            if (!$definition instanceof ChildDefinition || !$container->hasDefinition($definition->getParent())) {
                return false;
            }

            $definition = $container->getDefinition($definition->getParent());
        }
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
