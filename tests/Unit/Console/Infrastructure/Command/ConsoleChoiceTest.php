<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Infrastructure\Command;

use Gacela\Console\Infrastructure\Command\ConsoleChoice;
use PHPUnit\Framework\TestCase;

final class ConsoleChoiceTest extends TestCase
{
    public function test_an_accepted_value_has_nothing_to_report(): void
    {
        self::assertNull(ConsoleChoice::unknown('format', 'json', ['text', 'json']));
    }

    /**
     * The whole sentence: it names the option, the value that was typed and
     * every value that would have worked, and a reader acts on the last of
     * those. A substring assertion passes for any two of the three.
     */
    public function test_the_message_names_the_option_the_value_and_the_alternatives(): void
    {
        self::assertSame(
            '<error>Unknown format "xml". Use one of: text, mermaid, graphviz, json</error>',
            ConsoleChoice::unknown('format', 'xml', ['text', 'mermaid', 'graphviz', 'json']),
        );
    }

    /**
     * Matching is exact. `--format=JSON` is not `--format=json`, and silently
     * accepting it would be the same fallback this replaces, one case later.
     */
    public function test_matching_is_case_sensitive(): void
    {
        self::assertNotNull(ConsoleChoice::unknown('format', 'JSON', ['text', 'json']));
    }

    /**
     * The option name is a parameter because `profile:report` refuses two of
     * them, and "Unknown format" for a bad `--sort` sends the reader to the
     * wrong flag.
     */
    public function test_the_option_named_is_the_one_that_was_wrong(): void
    {
        self::assertSame(
            '<error>Unknown sort "meory". Use one of: duration, memory, operation</error>',
            ConsoleChoice::unknown('sort', 'meory', ['duration', 'memory', 'operation']),
        );
    }

    /**
     * Empty is not special here. `--format=` never reaches this method --
     * `ConsoleInput::format()` reads it as absent and answers with the default
     * -- so the only way to arrive with `''` is a caller that does not go
     * through it, and that caller is asking about a value no list contains.
     */
    public function test_an_empty_value_is_refused_like_any_other(): void
    {
        self::assertSame(
            '<error>Unknown format "". Use one of: text, json</error>',
            ConsoleChoice::unknown('format', '', ['text', 'json']),
        );
    }
}
