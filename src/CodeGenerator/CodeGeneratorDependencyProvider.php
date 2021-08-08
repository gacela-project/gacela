<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator;

use Gacela\CodeGenerator\Domain\FilenameSanitizer\FilenameSanitizer;
use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

/**
 * @method CodeGeneratorConfig getConfig()
 */
final class CodeGeneratorDependencyProvider extends AbstractDependencyProvider
{
    public const TEMPLATE_BY_FILENAME_MAP = 'TEMPLATE_FILENAME_MAP';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addTemplateByFilenameMap($container);
    }

    private function addTemplateByFilenameMap(Container $container): void
    {
        $codeTemplate = $this->getConfig();

        $container->set(self::TEMPLATE_BY_FILENAME_MAP, fn () => [
            FilenameSanitizer::FACADE => $codeTemplate->getFacadeMakerTemplate(),
            FilenameSanitizer::FACTORY => $codeTemplate->getFactoryMakerTemplate(),
            FilenameSanitizer::CONFIG => $codeTemplate->getConfigMakerTemplate(),
            FilenameSanitizer::DEPENDENCY_PROVIDER => $codeTemplate->getDependencyProviderMakerTemplate(),
        ]);
    }
}
