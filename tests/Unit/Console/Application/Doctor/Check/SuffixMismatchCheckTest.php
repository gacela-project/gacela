<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\SuffixMismatchCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Domain\AllAppModules\AppModule;
use PHPUnit\Framework\TestCase;

final class SuffixMismatchCheckTest extends TestCase
{
    public function test_no_modules_returns_ok(): void
    {
        $check = new SuffixMismatchCheck([], $this->defaultSuffixes());

        $result = $check->run();

        self::assertSame(CheckStatus::Ok, $result->status);
    }

    public function test_all_suffixes_match_defaults_returns_ok(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFacade',
            'App\\Foo\\FooFactory',
            'App\\Foo\\FooConfig',
            'App\\Foo\\FooProvider',
        );

        $check = new SuffixMismatchCheck([$module], $this->defaultSuffixes());

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function test_facade_with_wrong_suffix_is_error(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFaced', // typo suffix
        );

        $check = new SuffixMismatchCheck([$module], $this->defaultSuffixes());

        $result = $check->run();
        self::assertSame(CheckStatus::Error, $result->status);
        self::assertNotEmpty($result->details);
    }

    public function test_optional_factory_with_wrong_suffix_is_warning(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFacade',
            'App\\Foo\\FooFactorio', // bad
        );

        $result = (new SuffixMismatchCheck([$module], $this->defaultSuffixes()))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
    }

    public function test_custom_suffix_is_respected(): void
    {
        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooPublicApi',
        );

        $suffixes = $this->defaultSuffixes();
        $suffixes['Facade'][] = 'PublicApi';

        self::assertSame(CheckStatus::Ok, (new SuffixMismatchCheck([$module], $suffixes))->run()->status);
    }

    /**
     * @return array{Facade: list<string>, Factory: list<string>, Config: list<string>, Provider: list<string>}
     */
    private function defaultSuffixes(): array
    {
        return [
            'Facade' => ['Facade'],
            'Factory' => ['Factory'],
            'Config' => ['Config'],
            'Provider' => ['Provider'],
        ];
    }
}
