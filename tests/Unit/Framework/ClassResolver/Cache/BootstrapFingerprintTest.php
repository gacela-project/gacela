<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Cache;

use Gacela\Framework\ClassResolver\Cache\BootstrapFingerprint;
use PHPUnit\Framework\TestCase;

use function strlen;

final class BootstrapFingerprintTest extends TestCase
{
    public function test_the_same_inputs_always_answer_the_same(): void
    {
        self::assertSame(
            BootstrapFingerprint::compute(['App'], ['Factory' => 'Factory']),
            BootstrapFingerprint::compute(['App'], ['Factory' => 'Factory']),
        );
    }

    /**
     * Namespace order is priority, so it decides which candidate wins --
     * swapping it must swap the file too.
     */
    public function test_namespace_order_is_significant(): void
    {
        self::assertNotSame(
            BootstrapFingerprint::compute(['App', 'Vendor'], []),
            BootstrapFingerprint::compute(['Vendor', 'App'], []),
        );
    }

    /**
     * Suffix-type declaration order changes nothing about resolution, so two
     * bootstraps declaring the same map in different order must share a file.
     */
    public function test_suffix_type_declaration_order_is_not(): void
    {
        self::assertSame(
            BootstrapFingerprint::compute([], ['Factory' => ['F1', 'F2'], 'Config' => 'Conf']),
            BootstrapFingerprint::compute([], ['Config' => 'Conf', 'Factory' => ['F1', 'F2']]),
        );
    }

    public function test_different_namespaces_diverge(): void
    {
        self::assertNotSame(
            BootstrapFingerprint::compute(['App'], []),
            BootstrapFingerprint::compute(['Other'], []),
        );
    }

    public function test_different_suffix_types_diverge(): void
    {
        self::assertNotSame(
            BootstrapFingerprint::compute([], ['Factory' => 'FactoryA']),
            BootstrapFingerprint::compute([], ['Factory' => 'FactoryB']),
        );
    }

    /**
     * Filename-sized, same as the app-root hash next to it.
     */
    public function test_the_hash_is_twelve_characters(): void
    {
        self::assertSame(12, strlen(BootstrapFingerprint::compute(['App'], ['Factory' => 'Factory'])));
    }
}
