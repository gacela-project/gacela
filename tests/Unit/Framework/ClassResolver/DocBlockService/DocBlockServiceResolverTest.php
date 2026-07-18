<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\DocBlockService;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceResolver;
use Gacela\Framework\ClassResolver\Facade\FacadeResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class DocBlockServiceResolverTest extends TestCase
{
    public function test_default_class_for_facade_type_is_a_facade(): void
    {
        self::assertInstanceOf(AbstractFacade::class, $this->createDefault(FacadeResolver::TYPE));
    }

    public function test_default_class_for_factory_type_is_a_factory(): void
    {
        self::assertInstanceOf(AbstractFactory::class, $this->createDefault(FactoryResolver::TYPE));
    }

    public function test_default_class_for_config_type_is_a_config(): void
    {
        self::assertInstanceOf(AbstractConfig::class, $this->createDefault(ConfigResolver::TYPE));
    }

    public function test_default_class_for_unknown_type_is_null(): void
    {
        self::assertNull($this->createDefault('UnknownType'));
    }

    private function createDefault(string $resolvableType): ?object
    {
        $resolver = new DocBlockServiceResolver($resolvableType);

        return (new ReflectionMethod($resolver, 'createDefaultGacelaClass'))->invoke($resolver);
    }
}
