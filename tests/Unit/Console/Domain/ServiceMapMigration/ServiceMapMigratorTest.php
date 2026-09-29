<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ServiceMapMigration;

use Gacela\Console\Domain\ServiceMapMigration\ServiceMapMigrator;
use Gacela\StaticAnalysis\Rules\ServiceMapMissingAnalyser;
use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

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

            use Gacela\Framework\ServiceResolver\ServiceMap;
            use Gacela\Framework\ServiceResolverAwareTrait;

            /**
             * @method WalletFacade getFacade()
             */
            #[ServiceMap(method: 'getFacade', className: WalletFacade::class)]
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;
            }
            PHP, $result->migratedCode);
        $this->assertImportsOrdered($result->migratedCode);
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

    /**
     * An append would leave the import below ones that sort after it, and the
     * file would fail `ordered_imports` right after being migrated.
     */
    public function test_the_import_is_inserted_in_sorted_position(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Wallet;

            use App\Shared\Clock;
            use Gacela\Framework\ServiceResolverAwareTrait;
            use Symfony\Component\Console\Command\Command;

            use function sprintf;

            /** @method WalletFacade getFacade() */
            final class WalletCommand extends Command
            {
                use ServiceResolverAwareTrait;
            }
            PHP);

        self::assertSame(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Wallet;

            use App\Shared\Clock;
            use Gacela\Framework\ServiceResolver\ServiceMap;
            use Gacela\Framework\ServiceResolverAwareTrait;
            use Symfony\Component\Console\Command\Command;

            use function sprintf;

            /** @method WalletFacade getFacade() */
            #[ServiceMap(method: 'getFacade', className: WalletFacade::class)]
            final class WalletCommand extends Command
            {
                use ServiceResolverAwareTrait;
            }
            PHP, $result->migratedCode);
        $this->assertImportsOrdered($result->migratedCode);
    }

    public function test_the_import_goes_last_when_every_class_import_sorts_before_it(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use App\Shared\Clock;
            use Gacela\Framework\ServiceResolver\Resolver;

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use \Gacela\Framework\ServiceResolverAwareTrait;
            }
            PHP);

        self::assertStringContainsString(
            "use Gacela\\Framework\\ServiceResolver\\Resolver;\nuse Gacela\\Framework\\ServiceResolver\\ServiceMap;\n\n/**",
            $result->migratedCode,
        );
        $this->assertImportsOrdered($result->migratedCode);
    }

    /**
     * Classes come before functions and consts, so a file that imports only
     * those gets its class import as a group of its own above them.
     */
    public function test_the_import_goes_above_function_imports_when_there_are_no_class_imports(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use function sprintf;
            use const E_ALL;

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use \Gacela\Framework\ServiceResolverAwareTrait;
            }
            PHP);

        self::assertStringContainsString(
            "namespace App\\Wallet;\n\nuse Gacela\\Framework\\ServiceResolver\\ServiceMap;\n\nuse function sprintf;\nuse const E_ALL;\n",
            $result->migratedCode,
        );
        $this->assertImportsOrdered($result->migratedCode);
    }

    public function test_the_import_gets_its_own_block_when_the_file_imports_nothing(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use \Gacela\Framework\ServiceResolverAwareTrait;
            }
            PHP);

        self::assertStringContainsString(
            "namespace App\\Wallet;\n\nuse Gacela\\Framework\\ServiceResolver\\ServiceMap;\n\n/** @method",
            $result->migratedCode,
        );
    }

    /**
     * The formatter sorts aliased and group imports by their full text, alias
     * and braces included, so they are compared the same way here.
     */
    public function test_aliased_and_group_imports_keep_their_order(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use App\Shared\Clock as Zulu;
            use Gacela\Framework\{Config\Config, ServiceResolverAwareTrait};
            use Psr\Log\LoggerInterface as Alpha;

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;
            }
            PHP);

        self::assertStringContainsString(
            "use Gacela\\Framework\\{Config\\Config, ServiceResolverAwareTrait};\nuse Gacela\\Framework\\ServiceResolver\\ServiceMap;\nuse Psr\\Log\\LoggerInterface as Alpha;\n",
            $result->migratedCode,
        );
        $this->assertImportsOrdered($result->migratedCode);
    }

    public function test_a_group_of_classes_sorts_as_a_class_import(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use Zed\{Alpha, Beta};

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use \Gacela\Framework\ServiceResolverAwareTrait;
            }
            PHP);

        self::assertStringContainsString(
            "namespace App\\Wallet;\n\nuse Gacela\\Framework\\ServiceResolver\\ServiceMap;\nuse Zed\\{Alpha, Beta};\n\n/**",
            $result->migratedCode,
        );
        $this->assertImportsOrdered($result->migratedCode);
    }

    /**
     * A group holding a function is not a class import, so the class import
     * gets its own block above it.
     */
    public function test_a_group_holding_a_function_does_not_sort_as_a_class_import(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use Zed\{function helper, Thing};

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use \Gacela\Framework\ServiceResolverAwareTrait;
            }
            PHP);

        self::assertStringContainsString(
            "namespace App\\Wallet;\n\nuse Gacela\\Framework\\ServiceResolver\\ServiceMap;\n\nuse Zed\\{function helper, Thing};\n\n/**",
            $result->migratedCode,
        );
    }

    /**
     * A function import ahead of the class imports is passed over, not taken
     * as the end of them.
     */
    public function test_a_function_import_before_the_class_imports_is_passed_over(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use function sprintf;
            use App\Shared\Clock;

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use \Gacela\Framework\ServiceResolverAwareTrait;
            }
            PHP);

        self::assertStringContainsString(
            "use function sprintf;\nuse App\\Shared\\Clock;\nuse Gacela\\Framework\\ServiceResolver\\ServiceMap;\n\n/**",
            $result->migratedCode,
        );
    }

    public function test_a_file_without_a_namespace_gets_the_import_above_its_function_imports(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            use function sprintf;

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use \Gacela\Framework\ServiceResolverAwareTrait;
            }
            PHP);

        self::assertStringStartsWith(
            "<?php\n\nuse Gacela\\Framework\\ServiceResolver\\ServiceMap;\n\nuse function sprintf;\n\n/**",
            $result->migratedCode,
        );
    }

    /**
     * A comment above an import belongs to it, so the import goes above both.
     */
    public function test_the_import_goes_above_the_comment_of_the_import_it_precedes(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use App\Shared\Clock;
            // the console entry point
            use Symfony\Component\Console\Command\Command;

            /** @method WalletFacade getFacade() */
            final class WalletCommand extends Command
            {
                use \Gacela\Framework\ServiceResolverAwareTrait;
            }
            PHP);

        self::assertStringContainsString(
            "use App\\Shared\\Clock;\nuse Gacela\\Framework\\ServiceResolver\\ServiceMap;\n// the console entry point\nuse Symfony",
            $result->migratedCode,
        );
    }

    public function test_the_import_goes_below_a_multi_line_group(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use App\Shared\{
                Clock,
                Money,
            };

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use \Gacela\Framework\ServiceResolverAwareTrait;
            }
            PHP);

        self::assertStringContainsString(
            "    Money,\n};\nuse Gacela\\Framework\\ServiceResolver\\ServiceMap;\n\n/**",
            $result->migratedCode,
        );
        $this->assertImportsOrdered($result->migratedCode);
    }

    /**
     * Class names are case-insensitive, and a second import of the same name
     * would be a fatal error.
     */
    public function test_a_service_map_imported_in_another_case_is_not_imported_again(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use gacela\framework\serviceresolver\servicemap;

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use \Gacela\Framework\ServiceResolverAwareTrait;
            }
            PHP);

        self::assertTrue($result->hasChanges());
        self::assertSame(0, substr_count($result->migratedCode, 'use Gacela\Framework\ServiceResolver\ServiceMap;'));
    }

    public function test_a_service_map_imported_in_a_group_is_not_imported_again(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use Gacela\Framework\{ServiceResolver\ServiceMap, ServiceResolverAwareTrait};

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;
            }
            PHP);

        self::assertTrue($result->hasChanges());
        self::assertSame(1, substr_count($result->migratedCode, 'ServiceResolver\ServiceMap'));
    }

    /**
     * Under another alias the attribute's short name would not resolve, so
     * the plain import is still added.
     */
    public function test_a_service_map_imported_under_another_alias_is_imported_by_its_name(): void
    {
        $result = $this->migrator->migrate('Wallet.php', <<<'PHP'
            <?php

            namespace App\Wallet;

            use Gacela\Framework\ServiceResolver\ServiceMap as Map;
            use Gacela\Framework\ServiceResolverAwareTrait;

            /** @method WalletFacade getFacade() */
            final class WalletCommand
            {
                use ServiceResolverAwareTrait;
            }
            PHP);

        self::assertStringContainsString(
            "use Gacela\\Framework\\ServiceResolver\\ServiceMap;\nuse Gacela\\Framework\\ServiceResolver\\ServiceMap as Map;\n",
            $result->migratedCode,
        );
        $this->assertImportsOrdered($result->migratedCode);
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

    private function assertImportsOrdered(string $phpCode): void
    {
        $fixer = new OrderedImportsFixer();
        $fixer->configure(['imports_order' => ['class', 'function', 'const']]);

        $tokens = Tokens::fromCode($phpCode);
        $fixer->fix(new SplFileInfo(__FILE__), $tokens);

        self::assertSame($tokens->generateCode(), $phpCode, 'ordered_imports would reorder the migrated file');
    }
}
