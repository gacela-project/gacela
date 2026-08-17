<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Config\EnvironmentLayer;
use PHPUnit\Framework\TestCase;

/**
 * The rule that keeps `addAppConfig('config/*.php')` from reading every
 * environment's file into the base layer (#889).
 *
 * Every path below is built with the platform separator: the rule has to find
 * the filename inside an absolute path on Windows too, where every PR is also
 * run. Nothing here touches the filesystem -- the set of files is the one the
 * base pattern matched, and the rule is about their names.
 */
final class EnvironmentLayerTest extends TestCase
{
    private const DIRECTORY = 'project' . DIRECTORY_SEPARATOR . 'config';

    public function test_no_file_among_one_is_a_layer(): void
    {
        self::assertSame([], EnvironmentLayer::within([$this->path('app.php')]));
    }

    public function test_nothing_is_a_layer_when_no_file_was_matched(): void
    {
        self::assertSame([], EnvironmentLayer::within([]));
    }

    public function test_a_suffixed_file_is_a_layer_of_the_file_it_is_named_after(): void
    {
        $base = $this->path('app.php');
        $layer = $this->path('app-prod.php');

        $layers = EnvironmentLayer::within([$base, $layer]);

        self::assertSame([$layer], array_keys($layers));
        self::assertSame($base, $layers[$layer]->basePath);
        self::assertSame('prod', $layers[$layer]->suffix);
        self::assertSame($layer, $layers[$layer]->path);
    }

    /**
     * The base file, not the middle link. `app-prod-eu.php` is read when the
     * whole chain resolves to `prod-eu`, and reporting a suffix of `eu` would
     * name a value no chain ever resolves to on its own.
     */
    public function test_a_layer_two_links_deep_reports_the_base_file_and_the_whole_suffix(): void
    {
        $base = $this->path('app.php');
        $middle = $this->path('app-prod.php');
        $deepest = $this->path('app-prod-eu.php');

        $layers = EnvironmentLayer::within([$base, $middle, $deepest]);

        self::assertSame([$middle, $deepest], array_keys($layers));
        self::assertSame($base, $layers[$deepest]->basePath);
        self::assertSame('prod-eu', $layers[$deepest]->suffix);
        self::assertSame($base, $layers[$middle]->basePath);
        self::assertSame('prod', $layers[$middle]->suffix);
    }

    /**
     * A project may have `app.php` and `app-prod-eu.php` with no `app-prod.php`
     * between them -- a region that only production has -- so one strip is not
     * enough.
     */
    public function test_a_layer_is_found_across_a_missing_middle_link(): void
    {
        $base = $this->path('app.php');
        $layer = $this->path('app-prod-eu.php');

        $layers = EnvironmentLayer::within([$base, $layer]);

        self::assertSame([$layer], array_keys($layers));
        self::assertSame($base, $layers[$layer]->basePath);
        self::assertSame('prod-eu', $layers[$layer]->suffix);
    }

    /**
     * The whole point of anchoring the rule on another matched file: with nothing
     * to be a layer *of*, this file is the base layer, and excluding it would
     * turn a silent wrong value into a silent missing one.
     */
    public function test_a_suffixed_file_alone_is_not_a_layer(): void
    {
        self::assertSame([], EnvironmentLayer::within([$this->path('app-prod.php')]));
    }

    public function test_a_file_sharing_no_name_with_the_base_is_not_a_layer(): void
    {
        $layers = EnvironmentLayer::within([
            $this->path('app.php'),
            $this->path('queue-worker.php'),
        ]);

        self::assertSame([], $layers);
    }

    /**
     * `config/local.php` matches the same pattern as `config/app.php` and has no
     * suffix to strip, so it stays in the base layer.
     */
    public function test_a_file_with_no_dash_is_not_a_layer(): void
    {
        $layers = EnvironmentLayer::within([
            $this->path('app.php'),
            $this->path('local.php'),
        ]);

        self::assertSame([], $layers);
    }

    /**
     * {@see \Gacela\Framework\Config\PathNormalizer\WithSuffixAbsolutePathStrategy}
     * inserts the suffix before the *first* dot, so the rule has to strip it from
     * there and nowhere else.
     */
    public function test_a_multi_part_filename_is_stripped_before_the_first_dot(): void
    {
        $base = $this->path('default.app.php');
        $layer = $this->path('default-prod.app.php');

        $layers = EnvironmentLayer::within([$base, $layer]);

        self::assertSame([$layer], array_keys($layers));
        self::assertSame($base, $layers[$layer]->basePath);
        self::assertSame('prod', $layers[$layer]->suffix);
    }

    /**
     * A suffixed name whose base has a different extension is a different file,
     * not a layer: `app-prod.json` refines `app.json`, never `app.php`.
     */
    public function test_the_base_file_must_carry_the_same_extension(): void
    {
        $layers = EnvironmentLayer::within([
            $this->path('app.php'),
            $this->path('app-prod.json'),
        ]);

        self::assertSame([], $layers);
    }

    /**
     * The strategy above appends `-<suffix>` when there is no extension at all,
     * so a pattern like `config/*` is stripped the same way.
     */
    public function test_a_file_with_no_extension_is_stripped_too(): void
    {
        $base = $this->path('app');
        $layer = $this->path('app-prod');

        $layers = EnvironmentLayer::within([$base, $layer]);

        self::assertSame([$layer], array_keys($layers));
        self::assertSame('prod', $layers[$layer]->suffix);
    }

    /**
     * A leading dash is not a suffix separator: stripping it would leave no name
     * at all, and `-prod.php` refines nothing.
     */
    public function test_a_leading_dash_is_not_a_suffix_separator(): void
    {
        $layers = EnvironmentLayer::within([
            $this->path('.php'),
            $this->path('-prod.php'),
        ]);

        self::assertSame([], $layers);
    }

    /**
     * The same basename in two directories is two unrelated files. Only the
     * sibling beside it can be its base layer.
     */
    public function test_a_base_file_in_another_directory_is_not_the_base(): void
    {
        $layers = EnvironmentLayer::within([
            'project' . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'app.php',
            $this->path('app-prod.php'),
        ]);

        self::assertSame([], $layers);
    }

    /**
     * The exclusion can never empty a base layer: a layer is named after a file
     * with a strictly shorter basename, so whatever the pattern matched, the
     * shortest of them survives.
     */
    public function test_the_shortest_name_matched_is_never_a_layer(): void
    {
        $paths = [
            $this->path('app-prod-eu.php'),
            $this->path('app-prod.php'),
            $this->path('app.php'),
        ];

        $layers = EnvironmentLayer::within($paths);

        self::assertNotSame([], array_diff($paths, array_keys($layers)));
    }

    private function path(string $name): string
    {
        return self::DIRECTORY . DIRECTORY_SEPARATOR . $name;
    }
}
