<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinder;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidator;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidatorInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithModulePrefix;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithoutModulePrefix;
use Gacela\Framework\Config\Config;

final class ClassResolverFactory
{
    public const CACHED_CLASS_NAMES_FILE = 'gacela-cached-class-names.cache';

    public function createClassNameFinder(): ClassNameFinderInterface
    {
        return new ClassNameFinder(
            $this->createClassValidator(),
            $this->createFinderRules(),
            $this->createClassNameCache()
        );
    }

    public function createClassNameCache(): InMemoryClassNameCache
    {
        return new InMemoryClassNameCache(
            $this->getCachedClassNames(),
        );
    }

    private function createClassValidator(): ClassValidatorInterface
    {
        return new ClassValidator();
    }

    /**
     * @return list<FinderRuleInterface>
     */
    private function createFinderRules(): array
    {
        return [
            new FinderRuleWithModulePrefix(),
            new FinderRuleWithoutModulePrefix(),
        ];
    }

    /**
     * @return array<string,string>
     */
    private function getCachedClassNames(): array
    {
        $filename = $this->getCachedClassNamesDir() . self::CACHED_CLASS_NAMES_FILE;

        if (file_exists($filename)) {
            /** @var array<string,string> $content */
            $content = require $filename;

            return $content;
        }

        return [];
    }

    private function getCachedClassNamesDir(): string
    {
        return Config::getInstance()->getAppRootDir() . '/data/';
    }
}
