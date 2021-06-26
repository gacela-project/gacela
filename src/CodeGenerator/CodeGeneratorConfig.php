<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator;

use Gacela\CodeGenerator\Infrastructure\Template\CodeTemplateInterface;
use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Config;
use LogicException;

final class CodeGeneratorConfig extends AbstractConfig implements CodeTemplateInterface
{
    public function getFacadeMakerTemplate(): string
    {
        return $this->getCommandTemplateContent('facade-maker.txt');
    }

    public function getFactoryMakerTemplate(): string
    {
        return $this->getCommandTemplateContent('factory-maker.txt');
    }

    public function getConfigMakerTemplate(): string
    {
        return $this->getCommandTemplateContent('config-maker.txt');
    }

    public function getDependencyProviderMakerTemplate(): string
    {
        return $this->getCommandTemplateContent('dependency-provider-maker.txt');
    }

    private function getCommandTemplateContent(string $filename): string
    {
        return file_get_contents(__DIR__ . '/Infrastructure/Template/Command/' . $filename);
    }

    public function getComposerJsonContentAsArray(): array
    {
        $filename = Config::getApplicationRootDir() . '/composer.json';
        if (!file_exists($filename)) {
            throw new LogicException('composer.json file not found but it is required');
        }

        return (array)json_decode(file_get_contents($filename), true);
    }
}
