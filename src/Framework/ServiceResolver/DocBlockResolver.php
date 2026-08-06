<?php

declare(strict_types=1);

namespace Gacela\Framework\ServiceResolver;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockParser;
use Gacela\Framework\ClassResolver\DocBlockService\MissingClassDefinitionException;
use Gacela\Framework\ClassResolver\DocBlockService\UseBlockParser;
use ReflectionAttribute;
use ReflectionClass;

use function sprintf;
use function trigger_error;

use const E_USER_DEPRECATED;

final class DocBlockResolver
{
    private const SPECIAL_RESOLVABLE_TYPES = ['Facade', 'Factory', 'Config'];

    /**
     * One string, deliberately not a concatenation: splitting it for line length
     * generates Concat mutants that no reasonable assertion kills, and pinning
     * the exact rendered characters would turn a behavioural test into a
     * golden master over a message nobody reads character by character.
     */
    private const string FALLBACK_DEPRECATION = "Gacela: %s::%s() was resolved from %s. This fallback is deprecated and will be removed in 3.0. Declare it with #[ServiceMap(method: '%s', className: ...)] instead.";

    /** @var array<string,string> [fileName => fileContent] */
    private static array $fileContentCache = [];

    /** @var array<string,array<string,string>> [callerClass][method] => strategy already reported */
    private static array $warned = [];

    /**
     * @param class-string $callerClass
     */
    private function __construct(private readonly string $callerClass)
    {
    }

    public static function fromCaller(object $caller): self
    {
        return new self($caller::class);
    }

    /**
     * @param class-string $callerClass
     */
    public static function fromClassName(string $callerClass): self
    {
        return new self($callerClass);
    }

    public function getDocBlockResolvable(string $method): DocBlockResolvable
    {
        $className = $this->getClassName($method);
        $resolvableType = $this->normalizeResolvableType($className);

        return new DocBlockResolvable($className, $resolvableType);
    }

    /**
     * @return class-string
     */
    private function getClassName(string $method): string
    {
        $cacheKey = $this->generateCacheKey($method);
        $cache = ServiceResolverCache::getCacheInstance();

        if (!$cache->has($cacheKey)) {
            $className = $this->getClassFromDoc($method);
            $cache->put($cacheKey, $className);
        }

        return $cache->get($cacheKey);
    }

    private function generateCacheKey(string $method): string
    {
        return sprintf('%s::%s', $this->callerClass, $method);
    }

    /**
     * @return class-string
     */
    private function getClassFromDoc(string $method): string
    {
        $reflectionClass = ReflectionClassPool::get($this->callerClass);

        $className = $this->searchClassOverAttributes($reflectionClass, $method);
        if ($className !== null) {
            return $className;
        }

        $className = $this->searchClassOverDocBlock($reflectionClass, $method);
        if (class_exists($className)) {
            $this->triggerFallbackDeprecation($method, '@method docblock');

            return $className;
        }

        $className = $this->searchClassOverUseStatements($reflectionClass, $className);
        if (class_exists($className)) {
            $this->triggerFallbackDeprecation($method, "the file's use statements");

            return $className;
        }

        if ($method === 'getFactory') {
            return AbstractFactory::class;
        }

        throw MissingClassDefinitionException::missingDefinition($this->callerClass, $method, $className);
    }

    /**
     * Resolving a pillar from a `@method` docblock, or by scanning the caller's
     * `use` statements, is deprecated. Declare the pillar with `#[ServiceMap]`
     * instead: the attribute states the same fact where a reader and an
     * analyser can both see it, rather than leaving it to be re-derived from
     * source at runtime.
     *
     * Fires on a cold resolve only -- the answer is memoized per
     * caller-and-method, so a warm cache stays silent. Run `gacela cache:clear`
     * (or develop with the file cache off) to surface every occurrence.
     */
    private function triggerFallbackDeprecation(string $method, string $strategy): void
    {
        // Once per caller-and-method for the life of the process. The resolved
        // class is memoized, so without this the notice would fire again on
        // every cache reset -- repeating an identical message, and charging
        // sprintf() to a cold-resolution path that measured +28.59% on
        // FileCacheBench::bench_without_cache when it did.
        // Nested rather than a concatenated "class::method" key: building one
        // string generates Concat mutants that no assertion can kill, because
        // the separator and operand order cannot change which pairs are
        // distinct. Two dimensions make the uniqueness structural instead.
        if (isset(self::$warned[$this->callerClass][$method])) {
            return;
        }

        self::$warned[$this->callerClass][$method] = $strategy;

        trigger_error(
            sprintf(self::FALLBACK_DEPRECATION, $this->callerClass, $method, $strategy, $method),
            E_USER_DEPRECATED,
        );
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     */
    private function searchClassOverDocBlock(ReflectionClass $reflectionClass, string $method): string
    {
        $docBlock = $reflectionClass->getDocComment();
        if ($docBlock === false) {
            return '';
        }

        return (new DocBlockParser())->getClassFromMethod($docBlock, $method);
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     *
     * @return class-string|null
     */
    private function searchClassOverAttributes(ReflectionClass $reflectionClass, string $method): ?string
    {
        $attributes = $reflectionClass->getAttributes(ServiceMap::class, ReflectionAttribute::IS_INSTANCEOF);

        foreach ($attributes as $attribute) {
            /** @var ServiceMap $instance */
            $instance = $attribute->newInstance();
            if ($instance->method === $method) {
                return $instance->className;
            }
        }

        return null;
    }

    /**
     * Look the uses, to find the fully-qualified class name for the className.
     *
     * @param ReflectionClass<object> $reflectionClass
     */
    private function searchClassOverUseStatements(ReflectionClass $reflectionClass, string $className): string
    {
        $fileName = $reflectionClass->getFileName();
        if ($fileName === false) {
            return '';
        }

        if (!isset(self::$fileContentCache[$fileName])) {
            $content = file_get_contents($fileName);
            if ($content === false) {
                return '';
            }

            self::$fileContentCache[$fileName] = $content;
        }

        $phpFile = self::$fileContentCache[$fileName];

        return (new UseBlockParser())->getUseStatement($className, $phpFile);
    }

    private function normalizeResolvableType(string $resolvableType): string
    {
        /** @var non-empty-list<string> $resolvableTypeParts */
        $resolvableTypeParts = explode('\\', $resolvableType);
        $result = end($resolvableTypeParts);

        foreach (self::SPECIAL_RESOLVABLE_TYPES as $specialName) {
            if (str_contains($result, $specialName)) {
                return $specialName;
            }
        }

        return $result;
    }
}
