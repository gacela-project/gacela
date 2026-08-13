<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\TaggedServiceTargetCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Domain\AllAppModules\AppModule;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\SetProvider;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\StubFacade;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\ThrowingProvider;
use PHPUnit\Framework\TestCase;

/**
 * `Container::tagged()` resolves each id in turn and answers `null` for one
 * naming nothing, so the group a module iterates silently carries a hole and
 * the failure lands on the consumer as "Call to a member function ... on
 * null" -- pointing at the loop, not at the registration.
 */
final class TaggedServiceTargetCheckTest extends TestCase
{
    public function test_no_tags_is_ok(): void
    {
        $result = (new TaggedServiceTargetCheck([], [], []))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertContains('no tagged services registered', $result->details);
    }

    /**
     * The reason this cannot be a `class_exists()` check at bootstrap: a tag
     * may group plain service ids that no class name backs, and those are
     * answered by whichever Provider registers them.
     */
    public function test_a_plain_id_a_provider_registers_is_answered(): void
    {
        $check = new TaggedServiceTargetCheck(
            [$this->module(SetProvider::class)],
            ['validators' => [SetProvider::ID]],
            [],
        );

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function test_a_class_the_container_can_construct_is_answered(): void
    {
        $check = new TaggedServiceTargetCheck([], ['validators' => [StubFacade::class]], []);

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    /**
     * The count is the whole of what a passing run reports, so it is asserted
     * rather than left to read as "some".
     */
    public function test_the_passing_message_counts_every_id_across_every_tag(): void
    {
        $check = new TaggedServiceTargetCheck(
            [],
            ['validators' => [StubFacade::class, 'app.id'], 'renderers' => ['other.id']],
            ['app.id', 'other.id'],
        );

        self::assertContains('3 tagged id(s) answered', $check->run()->details);
    }

    public function test_an_id_the_app_container_provides_is_answered(): void
    {
        $check = new TaggedServiceTargetCheck([], ['validators' => ['app.id']], ['app.id']);

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    /**
     * The answered id comes first on purpose: skipping it must not abandon the
     * ids after it, and with the unanswered one first the skip's `continue`
     * and `break` are indistinguishable.
     */
    public function test_an_answered_id_does_not_end_the_walk_of_its_group(): void
    {
        $check = new TaggedServiceTargetCheck(
            [$this->module(SetProvider::class)],
            ['validators' => [SetProvider::ID, 'App\\Shop\\Ghost']],
            [],
        );

        $result = $check->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString('Ghost', $result->details[0]);
    }

    /**
     * The slot holds a class name, and nothing guarantees it is a Provider.
     * It is skipped rather than instantiated and run.
     */
    public function test_a_provider_slot_holding_a_non_provider_class_is_skipped(): void
    {
        $check = new TaggedServiceTargetCheck(
            [$this->module(StubFacade::class)],
            ['validators' => ['App\\Shop\\Ghost']],
            [],
        );

        $result = $check->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        // Exactly the unanswered id: the class is skipped, not run and crashed.
        self::assertCount(1, $result->details);
    }

    public function test_an_id_nothing_answers_warns_naming_the_id_and_the_tag(): void
    {
        $check = new TaggedServiceTargetCheck([], ['validators' => ['App\Shop\Ghost']], []);

        $result = $check->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString('App\Shop\Ghost', $result->details[0]);
        self::assertStringContainsString('validators', $result->details[0]);
    }

    /**
     * Only the unanswered one: a tag is a group, and reporting the whole group
     * because one member is wrong buries the member that is.
     */
    public function test_only_the_unanswered_id_of_a_group_is_reported(): void
    {
        $check = new TaggedServiceTargetCheck(
            [],
            ['validators' => [StubFacade::class, 'App\Shop\Ghost']],
            [],
        );

        $result = $check->run();

        self::assertCount(1, $result->details);
        self::assertStringContainsString('Ghost', $result->details[0]);
    }

    /**
     * A Provider that cannot run outside its deployment is reported instead of
     * crashing the diagnosis of every other one.
     */
    public function test_a_provider_that_throws_is_reported_rather_than_fatal(): void
    {
        $check = new TaggedServiceTargetCheck(
            [$this->module(ThrowingProvider::class)],
            ['validators' => [StubFacade::class]],
            [],
        );

        $result = $check->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString('failed to run', $result->details[0]);
    }

    public function test_a_module_without_a_provider_is_skipped(): void
    {
        $check = new TaggedServiceTargetCheck(
            [$this->module(null)],
            ['validators' => ['App\Shop\Ghost']],
            [],
        );

        $result = $check->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        // Exactly the unanswered id: a skipped module must not add a
        // provider-failure line of its own.
        self::assertCount(1, $result->details);
    }

    private function module(?string $providerClass): AppModule
    {
        return new AppModule(
            'App\TestModule',
            'TestModule',
            StubFacade::class,
            null,
            null,
            $providerClass,
        );
    }
}
