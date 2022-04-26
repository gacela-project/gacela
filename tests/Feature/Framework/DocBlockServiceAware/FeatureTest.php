<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\DocBlockServiceAware;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\DocBlockServiceAware\Module\Infrastructure\Command\HelloCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_custom_service(): void
    {
        $output = new BufferedOutput();

        (new HelloCommand())->run(
            $this->createStub(InputInterface::class),
            $output
        );

        $expected = <<<TXT
fake-admin(id:1)
fake-admin(id:2)
TXT;
        self::assertSame($expected, $output->fetch());
    }
}
