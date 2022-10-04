<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolverAware;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class DocBlockResolverCustomServicesAwareTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_existing_service(): void
    {
        $dummy = new DummyDocBlockResolverAware();
        $actual = $dummy->getRepository()->findName();

        self::assertSame('name', $actual);
    }

    public function test_service_as_interface(): void
    {
        $dummy = new DummyDocBlockResolverAware();
        $actual = $dummy->getService()->getName();

        self::assertSame('fake-service.name', $actual);
    }

    public function test_non_existing_service(): void
    {
        $this->expectExceptionMessage('Missing the concrete return type for the method `getRepository()`');

        $dummy = new BadDummyDocBlockResolverAware();
        $dummy->getRepository();
    }
}
