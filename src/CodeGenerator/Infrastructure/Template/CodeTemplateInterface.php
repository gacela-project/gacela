<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Infrastructure\Template;

interface CodeTemplateInterface
{
    public function getFacadeMakerTemplate(): string;

    public function getFactoryMakerTemplate(): string;

    public function getConfigMakerTemplate(): string;

    public function getDependencyProviderMakerTemplate(): string;
}
