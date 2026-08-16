<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ServiceMapMigration;

use Gacela\Console\Domain\ServiceMapMigration\ServiceMapMigrator;
use Gacela\StaticAnalysis\Rules\ServiceMapMissingAnalyser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class ServiceMapMigratorTest extends TestCase
{
    private ServiceMapMigrator $migrator;

    protected function setUp(): void
    {
        $this->migrator = new ServiceMapMigrator(
            (new ParserFactory())->createForNewestSupportedVersion(),
            new ServiceMapMissingAnalyser(),
        );
    }

    /**
     * The attribute lands below the docblock, never above it: an attribute
     * written above a docblock is not attached to the class node, so the file
     * would read as migrated to a human and as unmigrated to every tool.
     */
    public function test_the_attribute_is_written_below_the_docblock_and_the_import_added(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Wallet;

            use Gacela\Framework\ServiceResolverAwareTrait;

            /**
             * @method WalletFacade getFacade()
             */
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;
            }
            PHP);

        self::assertTrue($result->hasChanges());
        self::assertSame(['WalletCommand::getFacade()'], $result->declared);
        self::assertSame(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Wallet;

            use Gacela\Framework\ServiceResolverAwareTrait;
            use Gacela\Framework\ServiceResolver\ServiceMap;

            /**
             * @method WalletFacade getFacade()
             */
            #[ServiceMap(method: 'getFacade', className: WalletFacade::class)]
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;
            }
            PHP, $result->migratedCode);
    }

    /**
     * Nothing else in the file may move. This rewrites code somebody else
     * wrote, and a migration that reformats to add one line is not one anybody
     * runs twice.
     */
    public function test_only_the_added_lines_differ(): void
    {
        $original = <<<'PHP'
            <?php

            namespace App\Wallet;

            use Gacela\Framework\ServiceResolverAwareTrait;

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;

                    // deliberately odd indentation, kept
                public const X = 1;
            }
            PHP;

        $result = $this->migrator->migrate('Wallet.php', $original);

        $addedBack = str_replace(
            ["use Gacela\\Framework\\ServiceResolver\\ServiceMap;\n", "#[ServiceMap(method: 'getFacade', className: WalletFacade::class)]\n"],
            '',
            $result->migratedCode,
        );

        self::assertSame($original, $addedBack);
    }

    /**
     * The attribute stacks above one that is already there rather than
     * displacing it, and still stays under the docblock.
     */
    public function test_an_existing_attribute_is_kept(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use Gacela\Framework\ServiceResolverAwareTrait;

            /** @method WalletFacade getFacade() */
            #[SomeOther]
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;
            }
            PHP);

        self::assertStringContainsString(
            "#[ServiceMap(method: 'getFacade', className: WalletFacade::class)]\n#[SomeOther]\nfinal class",
            $result->migratedCode,
        );
    }

    public function test_every_missing_accessor_gets_its_own_attribute(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use Gacela\Framework\ServiceResolverAwareTrait;

            /**
             * @method WalletFacade getFacade()
             * @method WalletFactory getFactory()
             */
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;
            }
            PHP);

        self::assertSame(
            ['WalletCommand::getFacade()', 'WalletCommand::getFactory()'],
            $result->declared,
        );
        self::assertStringContainsString('className: WalletFacade::class)]', $result->migratedCode);
        self::assertStringContainsString('className: WalletFactory::class)]', $result->migratedCode);
    }

    /**
     * Running it twice must be the same as running it once, or a migration
     * that half-failed could not be re-run.
     */
    public function test_a_second_run_changes_nothing(): void
    {
        $original = <<<'PHP'
            <?php

            namespace App\Wallet;

            use Gacela\Framework\ServiceResolverAwareTrait;

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;
            }
            PHP;

        $once = $this->migrator->migrate('Wallet.php', $original);
        $twice = $this->migrator->migrate('Wallet.php', $once->migratedCode);

        self::assertTrue($once->hasChanges());
        self::assertFalse($twice->hasChanges());
        self::assertSame($once->migratedCode, $twice->migratedCode);
    }

    /**
     * The import is added once even when the file gains several attributes.
     */
    public function test_the_import_is_added_once(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use Gacela\Framework\ServiceResolverAwareTrait;

            /**
             * @method WalletFacade getFacade()
             * @method WalletFactory getFactory()
             */
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;
            }
            PHP);

        self::assertSame(1, substr_count($result->migratedCode, 'use Gacela\Framework\ServiceResolver\ServiceMap;'));
    }

    public function test_an_already_imported_service_map_is_not_imported_again(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use Gacela\Framework\ServiceResolver\ServiceMap;
            use Gacela\Framework\ServiceResolverAwareTrait;

            /**
             * @method WalletFacade getFacade()
             * @method WalletFactory getFactory()
             */
            #[ServiceMap(method: 'getFacade', className: WalletFacade::class)]
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;
            }
            PHP);

        self::assertSame(1, substr_count($result->migratedCode, 'use Gacela\Framework\ServiceResolver\ServiceMap;'));
        self::assertSame(['WalletCommand::getFactory()'], $result->declared);
    }

    /**
     * A file may hold a migrated class and an unmigrated one. Stopping at the
     * first class that needs nothing would leave the rest of the file behind,
     * silently -- the run would report success having done half the work.
     */
    public function test_a_later_class_is_migrated_when_an_earlier_one_needs_nothing(): void
    {
        $result = $this->migrator->migrate('Two.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use Gacela\Framework\ServiceResolverAwareTrait;

            final class AlreadyFine
            {
                use ServiceResolverAwareTrait;
            }

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;
            }
            PHP);

        self::assertSame(['WalletCommand::getFacade()'], $result->declared);
        self::assertStringContainsString(
            "#[ServiceMap(method: 'getFacade', className: WalletFacade::class)]\nfinal class WalletCommand",
            $result->migratedCode,
        );
    }

    /**
     * A file it cannot parse is a file it must not rewrite.
     */
    public function test_an_unparsable_file_is_left_alone(): void
    {
        $broken = "<?php\n\nfinal class Broken {";

        $result = $this->migrator->migrate('Broken.php', $broken);

        self::assertFalse($result->hasChanges());
        self::assertSame($broken, $result->migratedCode);
    }

    public function test_a_class_without_the_resolver_trait_is_left_alone(): void
    {
        $original = <<<'PHP'
            <?php

            namespace App\Wallet;

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
            }
            PHP;

        $result = $this->migrator->migrate('Wallet.php', $original);

        self::assertFalse($result->hasChanges());
        self::assertSame($original, $result->migratedCode);
    }

    /**
     * A type that cannot be written as `X::class` is skipped by the analyser,
     * so nothing is written for it here either -- the file is left for a human
     * rather than given code that fails when the attribute is read.
     */
    public function test_a_union_typed_accessor_is_left_for_a_human(): void
    {
        $original = <<<'PHP'
            <?php

            namespace App\Wallet;

            use Gacela\Framework\ServiceResolverAwareTrait;

            /** @method WalletFacade|OtherFacade getFacade() */
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;
            }
            PHP;

        $result = $this->migrator->migrate('Wallet.php', $original);

        self::assertFalse($result->hasChanges());
        self::assertSame($original, $result->migratedCode);
    }
}
