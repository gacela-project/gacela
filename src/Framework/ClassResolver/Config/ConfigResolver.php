<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Config;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\MissingPillarMethodException;

use function sprintf;

final class ConfigResolver extends AbstractClassResolver
{
    public const TYPE = 'Config';

    /** @var object|class-string|null */
    private object|string|null $caller = null;

    /**
     * @param object|class-string $caller
     */
    public function resolve(object|string $caller): AbstractConfig
    {
        // See FactoryResolver: kept so the stand-in can name the module.
        $this->caller = $caller;

        /** @var AbstractConfig $resolved */
        $resolved = $this->doResolve($caller);

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return self::TYPE;
    }

    protected function createDefaultGacelaClass(): AbstractConfig
    {
        $moduleName = ClassInfo::from($this->caller ?? self::class, self::TYPE)->getModuleName();
        $caller = $this->caller;

        return new class($moduleName, $caller) extends AbstractConfig {
            /**
             * @param object|class-string|null $caller
             */
            public function __construct(
                private readonly string $moduleName,
                private readonly object|string|null $caller,
            ) {
            }

            /**
             * @param list<mixed> $arguments
             */
            public function __call(string $name, array $arguments): never
            {
                throw MissingPillarMethodException::onDefault(
                    ConfigResolver::TYPE,
                    $this->moduleName,
                    $name,
                    sprintf('%s%s', $this->moduleName, ConfigResolver::TYPE),
                    $this->caller,
                );
            }
        };
    }
}
