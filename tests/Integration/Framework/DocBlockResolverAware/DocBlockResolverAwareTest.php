<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolverAware;

use PHPUnit\Framework\TestCase;

final class DocBlockResolverAwareTest extends TestCase
{
    public function test_qualified_class_name(): void
    {
        $docBlockResolverAware = new DummyDocBlockResolverAware();

        $actual = $docBlockResolverAware->getRepository()->findName();

        self::assertSame('name', $actual);
    }
}
