<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\Provider\ProviderNotFoundException;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class ResolutionCandidatesTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
    }

    public function test_not_found_exception_lists_the_candidates_tried(): void
    {
        $exception = new ProviderNotFoundException(self::class);

        $message = $exception->getMessage();

        self::assertStringContainsString('Tried resolving the following class names:', $message);
        // The two finder rules produce a module-prefixed and a bare candidate.
        self::assertStringContainsString('\\ClassResolver\\ClassResolverProvider', $message);
        self::assertStringContainsString('\\ClassResolver\\Provider', $message);
    }
}
