<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ClassResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\MissingPillarMethodException;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ClassResolver\MisnamedPillar\MisnamedPillarFacade;
use GacelaTest\Feature\Framework\ClassResolver\MissingPillar\NoFactoryFacade;
use PHPUnit\Framework\TestCase;

/**
 * A module with no `Factory` still resolves one -- an empty stand-in, so that a
 * module which never asks for it works without declaring one. The first call to
 * it is where that decision has to be explained.
 *
 * Before, PHP explained it: `Call to undefined method
 * Gacela\Framework\AbstractFactory@anonymous::createThing()`, naming neither
 * the module nor the file that is missing.
 */
final class MissingPillarMethodTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });
    }

    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_calling_a_factory_a_module_never_declared_names_the_module(): void
    {
        $factory = (new FactoryResolver())->resolve(NoFactoryFacade::class);

        $this->expectException(MissingPillarMethodException::class);
        $this->expectExceptionMessage('Module `MissingPillar` has no `Factory`, so `createThing()` has nowhere to be defined.');

        $factory->createThing();
    }

    /**
     * The filename is the thing to go and check, so the message spells it out.
     */
    public function test_the_message_names_the_class_to_add(): void
    {
        $factory = (new FactoryResolver())->resolve(NoFactoryFacade::class);

        $this->expectExceptionMessage('Add `MissingPillarFactory`');

        $factory->createThing();
    }

    public function test_a_config_a_module_never_declared_says_the_same(): void
    {
        $config = (new ConfigResolver())->resolve(NoFactoryFacade::class);

        $this->expectException(MissingPillarMethodException::class);
        $this->expectExceptionMessage('Module `MissingPillar` has no `Config`, so `retries()` has nowhere to be defined.');

        $config->retries();
    }

    /**
     * The common way a module ends up on the stand-in is not that the Factory
     * is missing -- it is that the Factory is written and misnamed. "Add
     * `MisnamedPillarFactory`" is then an instruction to write a file that is
     * already in the directory, so the file is named instead.
     */
    public function test_a_misnamed_factory_beside_the_module_is_named(): void
    {
        $factory = (new FactoryResolver())->resolve(MisnamedPillarFacade::class);

        $this->expectExceptionMessage(
            "Found in the module directory:\n"
            . '  - MisnamedPillarFactroy.php extends AbstractFactory under another name'
            . ' -- the resolver looks for `MisnamedPillarFactory`',
        );

        $factory->createThing();
    }

    /**
     * The hint follows the instruction it qualifies rather than replacing it:
     * adding the class is still the fix when there is nothing to rename.
     */
    public function test_the_hint_comes_after_the_class_to_add(): void
    {
        $message = $this->messageOfCallingCreateThingOn(MisnamedPillarFacade::class);

        $add = strpos($message, 'Add `MisnamedPillarFactory`');
        $hint = strpos($message, 'Found in the module directory:');

        self::assertIsInt($add);
        self::assertIsInt($hint);
        self::assertGreaterThan($add, $hint);
    }

    /**
     * A module with nothing to point at keeps the message it had. `MissingPillar`
     * really has no Factory beside it, which is the case the stand-in is for.
     */
    public function test_a_module_with_nothing_to_point_at_gets_no_hint(): void
    {
        $message = $this->messageOfCallingCreateThingOn(NoFactoryFacade::class);

        self::assertStringNotContainsString('Found in the module directory:', $message);
    }

    /**
     * The stand-in is only a problem when something calls it. A module that
     * declares no Factory and never reaches for one still resolves, which is
     * the behaviour the stand-in exists for.
     */
    public function test_resolving_it_without_calling_anything_is_still_fine(): void
    {
        $factory = (new FactoryResolver())->resolve(NoFactoryFacade::class);

        self::assertInstanceOf(\Gacela\Framework\AbstractFactory::class, $factory);
    }

    /**
     * @param class-string $facade
     */
    private function messageOfCallingCreateThingOn(string $facade): string
    {
        $factory = (new FactoryResolver())->resolve($facade);

        try {
            $factory->createThing();
        } catch (MissingPillarMethodException $missingPillarMethodException) {
            return $missingPillarMethodException->getMessage();
        }

        self::fail('the stand-in must refuse a call it has no method for');
    }
}
