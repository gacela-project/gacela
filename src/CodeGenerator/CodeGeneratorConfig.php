<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Config;
use JsonException;
use LogicException;

final class CodeGeneratorConfig extends AbstractConfig
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
        return (string)file_get_contents(__DIR__ . '/Infrastructure/Template/Command/' . $filename);
    }

    /**
     * @throws JsonException
     *
     * @return array{autoload: array{psr-4: array<string,string>}}
     */
    public function getComposerJsonContentAsArray(): array
    {
        $filename = Config::getInstance()->getApplicationRootDir() . '/composer.json';
        if (!file_exists($filename)) {
            throw new LogicException('composer.json file not found but it is required');
        }

        /** @var array{autoload: array{psr-4: array<string,string>}} $json */
        $json = (array)json_decode((string)file_get_contents($filename), true, 512, JSON_THROW_ON_ERROR);

        return $json;
    }
}
