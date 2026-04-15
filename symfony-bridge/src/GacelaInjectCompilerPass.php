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
use function count;
use function sprintf;

/**
 * Teaches Symfony's DependencyInjection container to honor Gacela's
 * {@see Inject} attribute on Symfony-managed services.
 *
 * For each service whose constructor has a parameter annotated with
 * `#[Inject]`, the pass rewrites that parameter's argument so Symfony
 * resolves the slot via Gacela's container (referenced as
 * `gacela.container` at runtime) instead of its own autowire.
 *
 * Runs before Symfony's autowire pass (the default for
 * {@see Symfony\Component\DependencyInjection\Compiler\PassConfig::TYPE_BEFORE_OPTIMIZATION}).
 *
 * The `gacela.container` service must be registered by the consumer —
 * typically via a bundle extension or bootstrap — and must expose a
 * `get(string $className): object` method. Gacela's
 * {@see \Gacela\Framework\Gacela::container()} satisfies this contract.
 *
 * If both Symfony and `#[Inject]` claim the same constructor parameter,
 * the pass fails the build with a message identifying the service id
 * and parameter name.
 */
final class GacelaInjectCompilerPass implements CompilerPassInterface
{
    public const DEFAULT_GACELA_SERVICE_ID = 'gacela.container';

    public function __construct(
        private readonly string $gacelaServiceId = self::DEFAULT_GACELA_SERVICE_ID,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            $this->processDefinition($id, $definition);
        }
    }

    private function processDefinition(string $id, Definition $definition): void
    {
        if ($definition->isAbstract() || $definition->isSynthetic()) {
            return;
        }

        /** @var class-string|null $class */
        $class = $definition->getClass();
        if ($class === null || !class_exists($class)) {
            return;
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $this->processParameter($id, $definition, $parameter);
        }
    }

    private function processParameter(
        string $id,
        Definition $definition,
        ReflectionParameter $parameter,
    ): void {
        $attributes = $parameter->getAttributes(Inject::class);
        if (count($attributes) === 0) {
            return;
        }

        /** @var Inject $inject */
        $inject = $attributes[0]->newInstance();
        $target = $this->resolveTarget($inject, $parameter);
        if ($target === null) {
            return;
        }

        $namedKey = '$' . $parameter->getName();
        $positionalKey = $parameter->getPosition();

        if ($this->definitionHasArgument($definition, $namedKey, $positionalKey)) {
            throw new RuntimeException(sprintf(
                'Gacela #[Inject] conflicts with an existing Symfony argument on service "%s" parameter "$%s". '
                . 'Remove the Symfony argument or drop the #[Inject] attribute.',
                $id,
                $parameter->getName(),
            ));
        }

        $definition->setArgument($namedKey, $this->createGacelaResolutionArgument($target));
    }

    /**
     * @return class-string|null
     */
    private function resolveTarget(Inject $inject, ReflectionParameter $parameter): ?string
    {
        if ($inject->implementation !== null) {
            /** @var class-string $implementation */
            $implementation = $inject->implementation;
            return $implementation;
        }

        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        /** @var class-string $name */
        $name = $type->getName();
        return $name;
    }

    private function definitionHasArgument(Definition $definition, string $namedKey, int $positionalKey): bool
    {
        $arguments = $definition->getArguments();

        if (array_key_exists($namedKey, $arguments)) {
            return true;
        }

        return array_key_exists($positionalKey, $arguments);
    }

    /**
     * @param class-string $target
     */
    private function createGacelaResolutionArgument(string $target): Definition
    {
        $argument = new Definition($target);
        $argument->setFactory([new Reference($this->gacelaServiceId), 'get']);
        $argument->setArguments([$target]);
        $argument->setPublic(false);

        return $argument;
    }
}
