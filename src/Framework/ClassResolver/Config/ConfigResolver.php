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

    /**
     * @param object|class-string $caller
     */
    /** @var object|class-string|null */
    private object|string|null $caller = null;

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

        return new class($moduleName) extends AbstractConfig {
            public function __construct(
                private readonly string $moduleName,
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
                );
            }
        };
    }
}
