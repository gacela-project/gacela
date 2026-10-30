<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function array_slice;
use function count;
use function is_object;
use function sprintf;

final class ClassInfo implements ClassInfoInterface
{
    public const MODULE_NAME_ANONYMOUS = 'module-name@anonymous';

    /** @var array<string,array<string,self>> */
    private static array $callerClassCache = [];

    public function __construct(
        private readonly string $callerModuleNamespace,
        private readonly string $callerModuleName,
        private readonly string $cacheKey,
        private readonly string $resolvableType = '',
        private readonly bool $globalNamespace = false,
    ) {
    }

    /**
     * Every entry holds a cache key built from the declared kinds, so it is
     * cleared by whoever changes them.
     *
     * @internal
     */
    public static function resetCache(): void
    {
        self::$callerClassCache = [];
    }

    /**
     * @param object|class-string $caller
     */
    public static function from(object|string $caller, string $resolvableType = ''): self
    {
        if (is_object($caller)) {
            return self::fromObject($caller, $resolvableType);
        }

        return self::fromString($caller, $resolvableType);
    }

    public static function contextFromFile(string $file): string
    {
        $file = str_replace('\\', '/', realpath($file) ?: $file);
        if (str_ends_with($file, '.php')) {
            $file = substr($file, 0, -4);
        }

        return str_replace('/', '\\', $file);
    }

    public function getModuleNamespace(): string
    {
        return $this->callerModuleNamespace;
    }

    public function getModuleName(): string
    {
        return $this->callerModuleName;
    }

    public function isInGlobalNamespace(): bool
    {
        return $this->globalNamespace;
    }

    public function getCacheKey(): string
    {
        return $this->cacheKey;
    }

    public function getResolvableType(): string
    {
        return $this->resolvableType;
    }

    public function toString(): string
    {
        return sprintf(
            '{callerModuleNamespace:"%s", callerModuleName:"%s", resolvableType:"%s", cacheKey:"%s"}',
            $this->callerModuleNamespace,
            $this->callerModuleName,
            $this->resolvableType,
            $this->cacheKey,
        );
    }

    private static function fromObject(object $callerObject, string $resolvableType): self
    {
        $callerClass = $callerObject::class;

        return self::fromString($callerClass, $resolvableType);
    }

    private static function fromString(string $callerClass, string $resolvableType): self
    {
        if (isset(self::$callerClassCache[$callerClass][$resolvableType])) {
            return self::$callerClassCache[$callerClass][$resolvableType];
        }

        if (preg_match('/@anonymous\x00(.+):[0-9]+\$/s', $callerClass, $matches) === 1) {
            return self::$callerClassCache[$callerClass][$resolvableType] = self::fromAnonymousFile($matches[1], $resolvableType);
        }

        if (!str_contains(ltrim($callerClass, '\\'), '\\')) {
            return self::$callerClassCache[$callerClass][$resolvableType] = self::fromGlobalClass($callerClass, $resolvableType);
        }

        /** @var non-empty-list<string> $callerClassParts */
        $callerClassParts = explode('\\', ltrim($callerClass, '\\'));
        $callerFullNamespace = implode('\\', array_slice($callerClassParts, 0, count($callerClassParts) - 1));

        $namespaceParts = explode('\\', $callerFullNamespace);
        array_pop($namespaceParts);
        $callerModuleNamespace = implode('\\', $namespaceParts);
        /**
         * @psalm-suppress InvalidArrayOffset
         *
         * @var string $callerModuleName
         */
        $callerModuleName = $callerClassParts[count($callerClassParts) - 2] ?? '';
        $cacheKey = self::cacheKey($callerFullNamespace, $resolvableType);

        $self = new self($callerModuleNamespace, $callerModuleName, $cacheKey, $resolvableType);
        self::$callerClassCache[$callerClass][$resolvableType] = $self;

        return $self;
    }

    /**
     * A pillar is told by its base class, not its name, so a type that only
     * ends in a pillar suffix (a mapped `PriceConfig`) is not that pillar.
     * Folding it onto the pillar's key would hand back the module's Config.
     */
    private static function cacheKey(string $namespace, string $resolvableType): string
    {
        $className = sprintf('\\%s\\%s', $namespace, $resolvableType);
        $kind = ResolvableType::fromClassName($resolvableType)->resolvableType();

        if ($kind !== $resolvableType && isset(ResolvableTypes::BUILT_IN[$kind])) {
            return $className;
        }

        return GlobalKey::fromClassName($className);
    }

    private static function fromGlobalClass(string $callerClass, string $resolvableType): self
    {
        $moduleName = ResolvableType::fromClassName(ltrim($callerClass, '\\'))->moduleName();

        return new self('', $moduleName, '\\' . $moduleName . $resolvableType, $resolvableType, true);
    }

    private static function fromAnonymousFile(string $file, string $resolvableType): self
    {
        $namespace = self::MODULE_NAME_ANONYMOUS . '\\' . self::contextFromFile($file);

        return new self(
            self::MODULE_NAME_ANONYMOUS,
            $namespace,
            self::cacheKey($namespace, $resolvableType),
            $resolvableType,
        );
    }
}
