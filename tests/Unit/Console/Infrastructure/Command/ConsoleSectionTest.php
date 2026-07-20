<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Infrastructure\Command;

use Gacela\Console\Infrastructure\Command\ConsoleSection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class ConsoleSectionTest extends TestCase
{
    public function test_title_renders_a_blank_padded_banner(): void
    {
        $output = new BufferedOutput();

        ConsoleSection::title($output, 'My Title');

        self::assertSame(
            "\nMy Title\n" . str_repeat('=', 60) . "\n\n",
            $output->fetch(),
        );
    }

    public function test_separator_renders_a_60_char_line(): void
    {
        $output = new BufferedOutput();

        ConsoleSection::separator($output);

        self::assertSame(str_repeat('=', 60) . "\n", $output->fetch());
    }
}
