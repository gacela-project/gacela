<?php

declare(strict_types=1);

namespace Gacela\Console;

use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
use Gacela\Console\Infrastructure\Command\CommandCatalog;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Config\Config;
use Symfony\Component\Console\Command\Command;

/**
 * @extends AbstractProvider<ConsoleConfig>
 */
final class ConsoleProvider extends AbstractProvider
{
    public const COMMANDS = 'COMMANDS';

    public const TEMPLATE_BY_FILENAME_MAP = 'TEMPLATE_FILENAME_MAP';

    public const SERVICE_TEMPLATE_BY_FILENAME_MAP = 'SERVICE_TEMPLATE_FILENAME_MAP';

    /**
     * @return list<Command>
     */
    #[Provides(self::COMMANDS)]
    public function commands(): array
    {
        return CommandCatalog::instances(Config::getInstance()->getAppRootDir());
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

    /**
     * The `service` template set: Facade and Factory variants wired to a
     * Domain service, plus the extra Service and FacadeTest files.
     *
     * @return array<string,string>
     */
    #[Provides(self::SERVICE_TEMPLATE_BY_FILENAME_MAP)]
    public function serviceTemplateByFilenameMap(): array
    {
        return [
            FilenameSanitizer::FACADE => $this->getConfig()->getServiceFacadeMakerTemplate(),
            FilenameSanitizer::FACTORY => $this->getConfig()->getServiceFactoryMakerTemplate(),
            FilenameSanitizer::CONFIG => $this->getConfig()->getConfigMakerTemplate(),
            FilenameSanitizer::PROVIDER => $this->getConfig()->getProviderMakerTemplate(),
            'Service' => $this->getConfig()->getServiceMakerTemplate(),
            'FacadeTest' => $this->getConfig()->getServiceFacadeTestMakerTemplate(),
        ];
    }
}
