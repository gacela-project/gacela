<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\Container\Tagged\EmailValidator;
use GacelaTest\Integration\Framework\Container\Tagged\PushValidator;
use GacelaTest\Integration\Framework\Container\Tagged\SmsValidator;
use GacelaTest\Integration\Framework\Container\Tagged\ValidatorInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_map;
use function iterator_to_array;

final class ContainerTagsTest extends TestCase
{
    protected function tearDown(): void
    {
        (new ReflectionClass(Gacela::class))->getMethod('resetCache')->invoke(null);
    }

    public function test_an_app_wide_tag_is_resolvable_from_the_container(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->tag([EmailValidator::class, SmsValidator::class], 'validators');
        });

        self::assertSame(['email', 'sms'], $this->namesTaggedIn(Gacela::container(), 'validators'));
    }

    public function test_a_single_id_does_not_have_to_be_wrapped_in_an_array(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->tag(EmailValidator::class, 'validators');
        });

        self::assertSame(['email'], $this->namesTaggedIn(Gacela::container(), 'validators'));
    }

    public function test_repeated_calls_add_to_the_same_tag_rather_than_replacing_it(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->tag(EmailValidator::class, 'validators');
            $config->tag(SmsValidator::class, 'validators');
        });

        // A tag is a collection: the second call must not win over the first.
        self::assertSame(['email', 'sms'], $this->namesTaggedIn(Gacela::container(), 'validators'));
    }

    public function test_the_same_id_tagged_twice_is_only_yielded_once(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->tag([EmailValidator::class, EmailValidator::class], 'validators');
            $config->tag(EmailValidator::class, 'validators');
        });

        self::assertSame(['email'], $this->namesTaggedIn(Gacela::container(), 'validators'));
    }

    public function test_two_tags_stay_separate(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->tag(EmailValidator::class, 'validators');
            $config->tag(SmsValidator::class, 'notifiers');
        });

        self::assertSame(['email'], $this->namesTaggedIn(Gacela::container(), 'validators'));
        self::assertSame(['sms'], $this->namesTaggedIn(Gacela::container(), 'notifiers'));
    }

    public function test_an_unknown_tag_yields_nothing(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->tag(EmailValidator::class, 'validators');
        });

        self::assertSame([], $this->namesTaggedIn(Gacela::container(), 'nobody-registered-this'));
    }

    public function test_a_tagged_id_is_resolved_through_the_container_so_a_binding_is_honoured(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->addBinding(ValidatorInterface::class, PushValidator::class);
            $config->tag(ValidatorInterface::class, 'validators');
        });

        // The tag names the interface; what comes out is what the binding says.
        self::assertSame(['push'], $this->namesTaggedIn(Gacela::container(), 'validators'));
    }

    public function test_app_wide_tags_reach_a_module_container(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->tag([EmailValidator::class, SmsValidator::class], 'validators');
        });

        // A module's container is seeded from the same app config, which is what
        // lets a Provider consume a tag it did not declare.
        $moduleContainer = Container::withConfig(Config::getInstance());

        self::assertSame(['email', 'sms'], $this->namesTaggedIn($moduleContainer, 'validators'));
    }

    public function test_a_module_local_tag_extends_the_app_wide_one_for_that_module_only(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->tag(EmailValidator::class, 'validators');
        });

        $moduleContainer = Container::withConfig(Config::getInstance());
        $moduleContainer->tag(PushValidator::class, 'validators');

        self::assertSame(['email', 'push'], $this->namesTaggedIn($moduleContainer, 'validators'));

        // ...and a sibling module, built from the same config, is unaffected:
        // module containers are separate, so a module-local tag cannot leak.
        $sibling = Container::withConfig(Config::getInstance());
        self::assertSame(['email'], $this->namesTaggedIn($sibling, 'validators'));
    }

    /**
     * @param callable(GacelaConfig):void $configFn
     */
    private function bootstrapWith(callable $configFn): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            $configFn($config);
        });
    }

    /**
     * @return list<string>
     */
    private function namesTaggedIn(Container $container, string $tag): array
    {
        /** @var list<ValidatorInterface> $validators */
        $validators = iterator_to_array($container->tagged($tag), false);

        return array_map(
            static fn (ValidatorInterface $validator): string => $validator->name(),
            $validators,
        );
    }
}
