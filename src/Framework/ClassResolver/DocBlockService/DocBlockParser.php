<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;

final class DocBlockParser
{
    public function getClassFromMethod(string $docBlock, string $method): string
    {
        if ($docBlock === '') {
            return '';
        }

        if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN') === 0) {
            $docBlock = str_replace("\n", PHP_EOL, $docBlock);
        }

        $lines = array_filter(
            explode(PHP_EOL, $docBlock),
            static fn (string $l): bool => str_contains($l, $method),
        );

        /** @var array<int, string> $lineSplit */
        $lineSplit = explode(' ', (string)reset($lines));

        $classFromMethod = $lineSplit[3] ?? '';
        if ($classFromMethod !== '') {
            return $classFromMethod;
        }

        if ($method === 'getFactory') {
            $factoryType = $this->parseFacadeTemplate($docBlock);
            if ($factoryType !== '') {
                return $factoryType;
            }

            return AbstractFactory::class;
        }

        if ($method === 'getConfig') {
            $configType = $this->parseFactoryTemplate($docBlock);
            if ($configType !== '') {
                return $configType;
            }

            return AbstractConfig::class;
        }

        return '';
    }

    private function parseFacadeTemplate(string $docBlock): string
    {
        if (preg_match('/@extends\s+[^<]*AbstractFacade<\s*([^>\s]+)\s*>/i', $docBlock, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    private function parseFactoryTemplate(string $docBlock): string
    {
        if (preg_match('/@extends\s+[^<]*AbstractFactory<\s*([^>\s]+)\s*>/i', $docBlock, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }
}
