<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigInterfacesMapping;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    private LocalConfig\Facade $facade;

    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, ['isWorking?' => 'yes!']);
        $this->facade = new LocalConfig\Facade();
    }

    public function test_mapping_interfaces_from_config(): void
    {
        self::assertSame(
            'Hello Gacela! Name: Chemaclass & Jesus',
            $this->facade->generateCompanyAndName()
        );
    }

    public function test_resolved_class(): void
    {
        self::assertSame(
            [
                'bool' => true,
                'string' => 'string',
                'int' => 1,
                'float' => 1.2,
                'array' => ['array'],
            ],
            $this->facade->generateResolvedClass()
        );
    }

    public function test_mapping_anon_class_callable(): void
    {
        self::assertSame(
            [true, 'string', 1, 1.2, ['array']],
            $this->facade->generateTypesAnonClassCallable()
        );
    }

    public function test_mapping_anon_class_function(): void
    {
        self::assertSame(
            [true, 'string', 1, 1.2, ['array']],
            $this->facade->generateTypesAnonClassFunction()
        );
    }

    public function test_mapping_abstract_anon_class_callable(): void
    {
        self::assertSame(
            [true, 'string', 1, 1.2, ['array']],
            $this->facade->generateAbstractTypesAnonClassCallable()
        );
    }

    public function test_mapping_abstract_anon_class_function(): void
    {
        self::assertSame(
            [true, 'string', 1, 1.2, ['array']],
            $this->facade->generateAbstractTypesAnonClassFunction()
        );
    }
}
