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

        $classFromMethod = $this->returnTypeOfMethodTag($docBlock, $method);

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

    /**
     * The return type of `@method <type> <name>(`, or an empty string when the
     * docblock declares no such tag.
     *
     * Matched as a tag rather than as "the first line containing the name":
     * that read the line's fourth space-separated token, so a sentence
     * mentioning your own accessor answered for it with whatever word sat in
     * that position -- `Use getFacade() to reach the module.` resolved
     * `getFacade()` to a class called `wrapper.`, and the failure arrived as
     * "Missing the concrete return type" pointing at a docblock that states it.
     *
     * `\b` rather than a required `(`, because a tag written without the
     * parameter list resolved before and still does. It also keeps a longer
     * accessor from answering for a shorter one: `getFacade` does not match
     * `getFacadeExtended`, where no boundary falls.
     */
    private function returnTypeOfMethodTag(string $docBlock, string $method): string
    {
        // Callers spell the method both ways -- `__call()` hands over a bare
        // name, and the resolver's own tests ask with the parentheses.
        $name = rtrim($method, '()');

        $pattern = '#@method\s+(?:static\s+)?([^\s(]+)\s+' . preg_quote($name, '#') . '\b#';

        if (preg_match($pattern, $docBlock, $matches) === 1) {
            return $matches[1];
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
