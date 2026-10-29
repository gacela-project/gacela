<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Gacela;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class AddGlobalContextTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::resetCache();
    }

    public function test_a_file_global_registered_before_bootstrap_still_resolves_after_bootstrap(): void
    {
        $root = new ReflectionProperty(Gacela::class, 'appRootDir');
        $previous = $root->getValue();
        $root->setValue(null, null);
        $config = new class() extends AbstractConfig {};

        try {
            Gacela::addGlobal($config, __FILE__);
            Gacela::bootstrap(__DIR__);

            self::assertSame($config, (new ConfigResolver())->resolve(new class() {}));
        } finally {
            $root->setValue(null, $previous);
        }
    }

    public function test_add_global_uses_caller_file_as_context(): void
    {
        Gacela::addGlobal(new class() extends AbstractConfig {});

        $context = ClassInfo::contextFromFile(__FILE__);
        $key = AnonymousGlobal::createCacheKey($context, ConfigResolver::TYPE);

        self::assertInstanceOf(AbstractConfig::class, AnonymousGlobal::getByKey($key));
    }
}
