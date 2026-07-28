<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Container\PlanCache;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\SharedPlanCache;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use PHPUnit\Framework\TestCase;

/**
 * Gacela hands every container it builds one plan cache, so the modules that
 * each own a container stop re-reflecting the classes they have in common.
 * What that must never do is leak *configuration* between them.
 */
final class SharedPlanCacheTest extends TestCase
{
    protected function setUp(): void
    {
        SharedPlanCache::resetCache();
    }

    protected function tearDown(): void
    {
        SharedPlanCache::resetCache();
    }

    public function test_a_plan_made_by_one_container_is_available_to_the_next(): void
    {
        (new Container())->get(StringValue::class);

        self::assertContains(StringValue::class, SharedPlanCache::getInstance()->classes());
    }

    public function test_an_explicit_plan_cache_keeps_a_container_out_of_the_shared_one(): void
    {
        $isolated = new PlanCache();

        (new Container([], [], [], $isolated))->get(StringValue::class);

        self::assertContains(StringValue::class, $isolated->classes());
        self::assertNotContains(StringValue::class, SharedPlanCache::getInstance()->classes());
    }

    public function test_sharing_plans_does_not_share_bindings(): void
    {
        $bound = new Container([StringValueInterface::class => StringValue::class]);
        $bound->get(StringValueInterface::class);

        $unbound = new Container();

        self::assertFalse($unbound->provides(StringValueInterface::class));
    }

    public function test_reset_forgets_the_plans_of_classes_this_process_may_redefine(): void
    {
        (new Container())->get(StringValue::class);

        SharedPlanCache::resetCache();

        self::assertSame([], SharedPlanCache::getInstance()->classes());
    }
}
