<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\PathNormalizer;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use Gacela\Framework\Config\PathNormalizer\AbsolutePathNormalizer;
use Gacela\Framework\Config\PathNormalizer\WithoutSuffixAbsolutePathStrategy;
use Gacela\Framework\Config\PathNormalizer\WithSuffixAbsolutePathStrategy;
use PHPUnit\Framework\TestCase;

final class AbsolutePathNormalizerTest extends TestCase
{
    private const APP_ROOT = '/app';

    public function test_the_plain_pattern_carries_no_suffix(): void
    {
        self::assertSame('/app/config/*.php', $this->normalizer()->normalizePathPattern($this->item()));
    }

    public function test_the_environment_pattern_carries_the_environment(): void
    {
        self::assertSame(
            '/app/config/*-prod.php',
            $this->normalizer()->normalizePathPatternWithEnvironment($this->item()),
        );
    }

    /**
     * With no dimension declared the chain is the environment pattern alone,
     * which is what keeps a project that uses no dimension reading exactly the
     * files it read before.
     */
    public function test_without_a_chain_the_suffixed_patterns_are_the_environment_alone(): void
    {
        self::assertSame(
            ['/app/config/*-prod.php'],
            $this->normalizer()->normalizePathPatternsWithSuffixes($this->item()),
        );
    }

    public function test_a_chain_yields_one_pattern_per_link_most_general_first(): void
    {
        $normalizer = $this->normalizer([
            new WithSuffixAbsolutePathStrategy(self::APP_ROOT, 'prod'),
            new WithSuffixAbsolutePathStrategy(self::APP_ROOT, 'prod-eu'),
        ]);

        self::assertSame(
            ['/app/config/*-prod.php', '/app/config/*-prod-eu.php'],
            $normalizer->normalizePathPatternsWithSuffixes($this->item()),
        );
    }

    public function test_the_local_path_carries_no_suffix(): void
    {
        $item = new GacelaConfigItem('config/*.php', 'config/local.php');

        self::assertSame('/app/config/local.php', $this->normalizer()->normalizePathLocal($item));
    }

    /**
     * @param list<WithSuffixAbsolutePathStrategy> $chain
     */
    private function normalizer(array $chain = []): AbsolutePathNormalizer
    {
        return new AbsolutePathNormalizer([
            AbsolutePathNormalizer::WITHOUT_SUFFIX => new WithoutSuffixAbsolutePathStrategy(self::APP_ROOT),
            AbsolutePathNormalizer::WITH_SUFFIX => new WithSuffixAbsolutePathStrategy(self::APP_ROOT, 'prod'),
        ], $chain);
    }

    private function item(): GacelaConfigItem
    {
        return new GacelaConfigItem('config/*.php');
    }
}
