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

        self::assertSame('fake-admin', $output->fetch());
    }
}
