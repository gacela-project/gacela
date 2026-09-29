<?php

declare(strict_types=1);

use Gacela\Console\Infrastructure\Command\DtoGenerateCommand;
use GacelaTest\Feature\ReferenceApp\Support\ReferenceApp;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

// bin/gacela cannot do this: from the application's directory it bootstraps the
// repository root, and the application's gacela.php needs the clock its host
// supplies. ReferenceApp::bootstrap() is that host.
require \dirname(__DIR__, 3) . '/vendor/autoload.php';

ReferenceApp::bootstrap();

exit((new DtoGenerateCommand())->run(new ArgvInput(), new ConsoleOutput()));
