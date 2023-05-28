<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CodeGenerator;

use Gacela\Console\Domain\ConsoleException;
use Gacela\Console\Infrastructure\Command\MakeFileCommand;
use Gacela\Console\Infrastructure\Command\MakeModuleCommand;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class MakeExceptionCommandTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__ . '/undefined-folder/');
    }

    public function test_make_module_exception_when_composer_file_not_found(): void
    {
        $this->expectExceptionObject(ConsoleException::composerJsonNotFound());

        $input = new StringInput('Psr4CodeGenerator/TestModule');
        $bootstrap = new MakeModuleCommand();
        $bootstrap->run($input, new BufferedOutput());
    }

    public function test_make_file_exception_when_composer_file_not_found(): void
    {
        $this->expectExceptionObject(ConsoleException::composerJsonNotFound());

        $input = new StringInput('Psr4CodeGenerator/TestModule facade');
        $bootstrap = new MakeFileCommand();
        $bootstrap->run($input, new BufferedOutput());
    }
}
