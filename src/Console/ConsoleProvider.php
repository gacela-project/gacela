<?php

declare(strict_types=1);

namespace Gacela\Console;

use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
use Gacela\Console\Infrastructure\Command\CacheWarmCommand;
use Gacela\Console\Infrastructure\Command\DebugContainerCommand;
use Gacela\Console\Infrastructure\Command\DebugDependenciesCommand;
use Gacela\Console\Infrastructure\Command\DebugModulesCommand;
use Gacela\Console\Infrastructure\Command\DoctorCommand;
use Gacela\Console\Infrastructure\Command\ListModulesCommand;
use Gacela\Console\Infrastructure\Command\MakeFileCommand;
use Gacela\Console\Infrastructure\Command\MakeModuleCommand;
use Gacela\Console\Infrastructure\Command\ProfileReportCommand;
use Gacela\Console\Infrastructure\Command\ValidateConfigCommand;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;

/**
 * @extends AbstractProvider<ConsoleConfig>
 */
final class ConsoleProvider extends AbstractProvider
{
    public const COMMANDS = 'COMMANDS';

    public const TEMPLATE_BY_FILENAME_MAP = 'TEMPLATE_FILENAME_MAP';

    /**
     * @return list<object>
     */
    #[Provides(self::COMMANDS)]
    public function commands(): array
    {
        return [
            new MakeFileCommand(),
            new MakeModuleCommand(),
            new ListModulesCommand(),
            new DebugContainerCommand(),
            new DebugDependenciesCommand(),
            new DebugModulesCommand(),
            new CacheWarmCommand(),
            new ValidateConfigCommand(),
            new ProfileReportCommand(),
            new DoctorCommand(),
        ];
    }

    /**
     * @return array<string,string>
     */
    #[Provides(self::TEMPLATE_BY_FILENAME_MAP)]
    public function templateByFilenameMap(): array
    {
        return [
            FilenameSanitizer::FACADE => $this->getConfig()->getFacadeMakerTemplate(),
            FilenameSanitizer::FACTORY => $this->getConfig()->getFactoryMakerTemplate(),
            FilenameSanitizer::CONFIG => $this->getConfig()->getConfigMakerTemplate(),
            FilenameSanitizer::PROVIDER => $this->getConfig()->getProviderMakerTemplate(),
        ];
    }
}
