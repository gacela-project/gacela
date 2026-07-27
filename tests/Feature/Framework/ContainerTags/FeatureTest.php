<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerTags;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ContainerTags\Validation\EmailValidator;
use GacelaTest\Feature\Framework\ContainerTags\Validation\NotEmptyValidator;
use PHPUnit\Framework\TestCase;

use function array_slice;

final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->tag([NotEmptyValidator::class, EmailValidator::class], 'validators');
        });
    }

    public function test_a_module_consumes_an_app_wide_tag_it_did_not_declare(): void
    {
        $names = (new Checkout\Facade())->validatorNames();

        // The two app-wide validators come first, in the order they were tagged.
        self::assertSame(['not-empty', 'email'], array_slice($names, 0, 2));
    }

    public function test_a_module_adds_its_own_service_to_the_app_wide_tag(): void
    {
        self::assertSame(
            ['not-empty', 'email', 'card'],
            (new Checkout\Facade())->validatorNames(),
        );
    }

    public function test_one_module_local_contribution_does_not_leak_into_a_sibling(): void
    {
        // Both modules tag under 'validators', but each tags into its own
        // container -- so Shipping sees the app-wide pair plus its own, and
        // never Checkout's.
        self::assertSame(
            ['not-empty', 'email', 'address'],
            (new Shipping\Facade())->validatorNames(),
        );

        self::assertSame(
            ['not-empty', 'email', 'card'],
            (new Checkout\Facade())->validatorNames(),
        );
    }
}
