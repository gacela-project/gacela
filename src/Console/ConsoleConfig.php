<?php

declare(strict_types=1);

namespace Gacela\Console;

use Gacela\Console\Domain\ConsoleException;
use Gacela\Framework\AbstractConfig;
use JsonException;

final class ConsoleConfig extends AbstractConfig
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

    public function getProviderMakerTemplate(): string
    {
        return $this->getCommandTemplateContent('provider-maker.txt');
    }

    /**
     * @psalm-suppress MixedReturnTypeCoercion
     *
     * @throws ConsoleException|JsonException
     *
     * @return array{autoload: array{"psr-4": array<string,string>}}
     */
    public function getComposerJsonContentAsArray(): array
    {
        $filename = $this->getAppRootDir() . '/composer.json';
        if (!file_exists($filename)) {
            throw ConsoleException::composerJsonNotFound();
        }

        /** @var string $content */
        $content = file_get_contents($filename);

        /** @var array{autoload: array{"psr-4": array<string,string>}} $jsonDecode */
        $jsonDecode = json_decode(json: $content, associative: true, flags: JSON_THROW_ON_ERROR);

        return $jsonDecode;
    }

    private function getCommandTemplateContent(string $filename): string
    {
        /** @var string $content */
        $content = file_get_contents(__DIR__ . '/Infrastructure/Template/Command/' . $filename);

        return $content;
    }
}
