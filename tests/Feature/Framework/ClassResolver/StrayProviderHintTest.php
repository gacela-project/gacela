<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\Provider\ProviderNotFoundException;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ClassResolver\StrayProvider\StrayProviderFactory;
use PHPUnit\Framework\TestCase;

final class StrayProviderHintTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
    }

    /**
     * The message says "you can fix this by adding the missing `Provider`",
     * which is wrong whenever the Provider is written and misnamed -- the
     * common way this fails. The hint is what makes the rest of the message
     * readable in that case, so it is asserted in the real message rather than
     * only against the finder.
     */
    public function test_the_message_names_the_misnamed_file_in_the_callers_directory(): void
    {
        $message = (new ProviderNotFoundException(StrayProviderFactory::class))->getMessage();

        self::assertStringContainsString("Found in the module directory:\n", $message);
        self::assertStringContainsString(
            '  - StrayProviderProvidr.php extends AbstractProvider, under a name none of'
            . " the candidates above has -- rename it to one of them\n",
            $message,
        );
    }

    /**
     * Each hint is its own line. Counted rather than matched in order, because
     * the directory listing decides the order and this is about the newline
     * between them: dropped, the two hints run together on one line and only
     * one line matches.
     */
    public function test_each_hint_is_its_own_line(): void
    {
        $message = (new ProviderNotFoundException(StrayProviderFactory::class))->getMessage();

        preg_match_all('/^ {2}- (\S+\.php) /m', $message, $matches);

        self::assertSame(
            ['StrayProviderProviderOld.php', 'StrayProviderProvidr.php'],
            $this->sorted($matches[1]),
        );
    }

    /**
     * Under the candidates and above the tips: the hint is about one of the
     * names in that list, and the tips are the generic advice it supersedes.
     */
    public function test_the_hint_sits_between_the_candidates_and_the_tips(): void
    {
        $message = (new ProviderNotFoundException(StrayProviderFactory::class))->getMessage();

        $candidates = strpos($message, 'Tried resolving the following class names:');
        $hint = strpos($message, 'Found in the module directory:');
        $tips = strpos($message, 'Tips:');

        self::assertIsInt($candidates);
        self::assertIsInt($hint);
        self::assertIsInt($tips);
        self::assertGreaterThan($candidates, $hint);
        self::assertGreaterThan($hint, $tips);
    }

    /**
     * A directory with nothing to point at gets no block at all, rather than an
     * empty heading. This test class's own directory is that case.
     *
     * It is also the case that caught the hint reading files as text: this very
     * file contains the phrase "extends AbstractProvider" in the assertion
     * above, and was reported as the missing Provider for saying so. The phrase
     * is asserted to still be here, so the guard cannot quietly stop guarding
     * when that assertion is reworded.
     */
    public function test_a_file_that_only_mentions_the_base_class_is_not_reported(): void
    {
        $ownSource = file_get_contents(__FILE__);

        self::assertIsString($ownSource);
        self::assertStringContainsString(
            'extends AbstractProvider',
            $ownSource,
            'this test needs a file that names the base class without extending it',
        );

        $message = (new ProviderNotFoundException(self::class))->getMessage();

        self::assertStringNotContainsString('Found in the module directory:', $message);
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function sorted(array $names): array
    {
        sort($names);

        return $names;
    }
}
