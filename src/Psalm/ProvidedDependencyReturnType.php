<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Gacela\Framework\AbstractFactory;
use PhpParser\Node\Arg;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface;
use Psalm\Type\Atomic\TNamedObject;
use Psalm\Type\Union;

use function class_exists;
use function count;
use function interface_exists;

/**
 * Types `getProvidedDependency(Foo::class)` as `Foo`, the way
 * {@see \Gacela\PHPStan\Reflection\GetProvidedDependencyReturnTypeExtension}
 * does for PHPStan.
 *
 * The signature returns `mixed` because the key is a plain string, so every call
 * site restores the type by hand with a `@var` the analyser has to take on faith
 * -- and which keeps claiming the old type after the Provider changes. When the
 * key *is* a class-string, the type was never unknown; it was thrown away at the
 * boundary.
 *
 * A string key (`'some.service'`) still returns `mixed`. Nothing in the type
 * system says what it resolves to, and a guess would be worse than `mixed`:
 * `mixed` is honestly unknown, a guess is confidently wrong and trusted.
 */
final class ProvidedDependencyReturnType implements MethodReturnTypeProviderInterface
{
    private const METHOD = 'getprovideddependency';

    public static function getClassLikeNames(): array
    {
        return [AbstractFactory::class];
    }

    public static function getMethodReturnType(MethodReturnTypeProviderEvent $event): ?Union
    {
        if ($event->getMethodNameLowercase() !== self::METHOD) {
            return null;
        }

        $args = $event->getCallArgs();
        if (count($args) !== 1) {
            return null;
        }

        $className = self::constantStringArgument($event, $args[0]);

        // Returning null rather than mixed leaves the declared return type in
        // place, and lets another plugin have a go at the same call.
        if ($className === null) {
            return null;
        }

        // Resolved through the autoloader rather than Psalm's own codebase so
        // that this and the PHPStan extension answer identically for the same
        // key -- two analysers disagreeing about what a Provider supplies would
        // be worse than either of them being conservative.
        if (!class_exists($className) && !interface_exists($className)) {
            return null;
        }

        return new Union([new TNamedObject($className)]);
    }

    /**
     * Read through the inferred type, not the AST, so a class-string held in a
     * variable resolves the same as one written at the call site.
     */
    private static function constantStringArgument(
        MethodReturnTypeProviderEvent $event,
        Arg $arg,
    ): ?string {
        $type = $event->getSource()->getNodeTypeProvider()->getType($arg->value);
        if (!$type instanceof Union) {
            return null;
        }

        foreach ($type->getLiteralStrings() as $literal) {
            return $literal->value;
        }

        return null;
    }
}
