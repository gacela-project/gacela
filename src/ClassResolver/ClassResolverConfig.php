<?php

declare(strict_types=1);

namespace Gacela\ClassResolver;

use Gacela\AbstractConfig;
use Gacela\ClassResolver\ClassNameCandidatesBuilder\ClassNameBuilderConfigInterface;

class ClassResolverConfig extends AbstractConfig implements ClassNameBuilderConfigInterface
{
    public const PROJECT_NAMESPACE = 'PROJECT_NAMESPACE';

    public function getProjectNamespace(): string
    {
        return (string)$this->get(self::PROJECT_NAMESPACE);
    }

    /**
     * Key consist of:
     * - the resolvable type.
     *
     * Placeholders value:
     * - the project namespace
     * - the module name
     *
     * @return array<string,string>
     */
    public function getResolvableTypeClassNamePatternMap(): array
    {
        return [
            'Config' => '\\%s\\%s\\%sConfig',
            'DependencyProvider' => '\\%s\\%s\\%sDependencyProvider',
            'Facade' => '\\%s\\%s\\%sFacade',
            'Factory' => '\\%s\\%s\\%sFactory',
        ];
    }
}
