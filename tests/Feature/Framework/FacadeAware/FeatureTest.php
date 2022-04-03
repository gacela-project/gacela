<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FacadeAware;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\FacadeAware\Module\Command\TestHiCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_facade_aware(): void
    {
        $output = new BufferedOutput();

        $hiCommand = new TestHiCommand();

        $hiCommand->run(
            $this->createStub(InputInterface::class),
            $output
        );

        self::assertSame('Hi', $output->fetch());
    }
}
