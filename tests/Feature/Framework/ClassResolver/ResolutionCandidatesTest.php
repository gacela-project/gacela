<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ClassResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\Provider\ProviderNotFoundException;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function array_unique;
use function array_values;
use function preg_match_all;

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

        // Asked of the bulleted list rather than the whole message: the `E.g.`
        // line names the module-prefixed class too, so a message-wide search
        // for it passes even with the prefix finder rule removed -- which is
        // the one thing this test is here to notice.
        $candidates = $this->candidateLines($message);

        // The two finder rules produce a module-prefixed and a bare candidate.
        self::assertContains(
            'ClassResolverProvider',
            $this->shortNames($candidates),
            'the module-prefixed candidate is missing from the list',
        );
        self::assertContains(
            'Provider',
            $this->shortNames($candidates),
            'the bare candidate is missing from the list',
        );
    }

    public function test_candidates_are_rendered_as_a_bulleted_block(): void
    {
        $exception = new ProviderNotFoundException(self::class);

        $message = $exception->getMessage();

        self::assertStringStartsWith('ClassResolver Exception' . "\n", $message);
        self::assertStringContainsString("Tried resolving the following class names:\n  - ", $message);

        preg_match_all('/^ {2}- (\S+)$/m', $message, $matches);
        self::assertNotSame([], $matches[1], 'each candidate renders as its own "  - x" line');
    }

    public function test_candidates_are_deduplicated(): void
    {
        // Declaring the module's own namespace as a project namespace makes
        // both loops build identical candidates; the list must dedupe them.
        $moduleNamespace = ClassInfo::from(self::class, 'Provider')->getModuleNamespace();
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($moduleNamespace): void {
            $config->setProjectNamespaces([$moduleNamespace]);
        });

        $message = (new ProviderNotFoundException(self::class))->getMessage();

        preg_match_all('/^ {2}- (\S+)$/m', $message, $matches);
        self::assertNotSame([], $matches[1]);
        self::assertSame(array_values(array_unique($matches[1])), $matches[1]);
    }

    /**
     * @return list<string>
     */
    private function candidateLines(string $message): array
    {
        preg_match_all('/^ {2}- (\S+)$/m', $message, $matches);

        /** @var list<string> $candidates */
        $candidates = $matches[1];

        return $candidates;
    }

    /**
     * @param list<string> $candidates
     *
     * @return list<string>
     */
    private function shortNames(array $candidates): array
    {
        return array_values(array_map(
            static fn (string $c): string => substr((string)strrchr($c, '\\'), 1),
            $candidates,
        ));
    }
}
