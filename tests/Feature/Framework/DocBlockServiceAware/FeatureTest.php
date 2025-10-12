<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\DocBlockServiceAware;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\DocBlockServiceAware\Module\Infrastructure\Command\HelloCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    public function test_custom_service(): void
    {
        $output = new BufferedOutput();

        (new HelloCommand())->run(
            $this->createStub(InputInterface::class),
            $output,
        );

        $expected = <<<TXT
fake-admin(id:1)
fake-admin(id:2)
TXT;

        if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN') == 0) {
            $expected = str_replace("\n", PHP_EOL, $expected);
        }

        self::assertSame($expected, $output->fetch());
    }
}
