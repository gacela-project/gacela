<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Factory;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\MissingPillarMethodException;

use function sprintf;

final class FactoryResolver extends AbstractClassResolver
{
    public const TYPE = 'Factory';

    /** @var object|class-string|null */
    private object|string|null $caller = null;

    /**
     * @param object|class-string $caller
     */
    public function resolve(object|string $caller): AbstractFactory
    {
        // Kept so the stand-in below can name the module. doResolve() does not
        // hand it on, and widening that protected signature would break every
        // resolver a project has subclassed.
        $this->caller = $caller;

        /** @var AbstractFactory $resolved */
        $resolved = $this->doResolve($caller);

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return self::TYPE;
    }

    protected function createDefaultGacelaClass(): AbstractFactory
    {
        $moduleName = ClassInfo::from($this->caller ?? self::class, self::TYPE)->getModuleName();

        return new /**
         * @extends AbstractFactory<AbstractConfig>
         */ class($moduleName) extends AbstractFactory {
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
                    FactoryResolver::TYPE,
                    $this->moduleName,
                    $name,
                    sprintf('%s%s', $this->moduleName, FactoryResolver::TYPE),
                );
            }
        };
    }
}
