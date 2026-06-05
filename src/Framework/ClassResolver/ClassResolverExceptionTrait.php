<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithModulePrefix;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithoutModulePrefix;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Exception\ErrorSuggestionHelper;
use Throwable;

use function array_unique;
use function array_values;
use function sprintf;

trait ClassResolverExceptionTrait
{
    /**
     * @param object|class-string $caller
     */
    private function buildMessage(object|string $caller, string $resolvableType): string
    {
        $callerClassInfo = ClassInfo::from($caller, $resolvableType);

        $message = 'ClassResolver Exception' . "\n";
        $message .= sprintf(
            'Cannot resolve the `%s` for your module `%s`',
            $resolvableType,
            $callerClassInfo->getModuleName(),
        ) . "\n";

        $message .= sprintf(
            'You can fix this by adding the missing `%s` to your module.',
            $resolvableType,
        ) . "\n";

        $message .= sprintf(
            'E.g. `%s`',
            $this->findClassNameExample($callerClassInfo, $resolvableType),
        ) . "\n";

        $candidates = $this->candidatesTried($callerClassInfo, $resolvableType);
        if ($candidates !== []) {
            $message .= 'Tried resolving the following class names:' . "\n";
            foreach ($candidates as $candidate) {
                $message .= '  - ' . $candidate . "\n";
            }
        }

        return $message . ErrorSuggestionHelper::addHelpfulTip('facade_not_found');
    }

    /**
     * Reproduce the class-name candidates the finder would attempt, so the
     * developer can see exactly which names were looked up and why the lookup
     * failed (e.g. a naming-convention or namespace mismatch).
     *
     * @return list<string>
     */
    private function candidatesTried(ClassInfo $classInfo, string $resolvableType): array
    {
        try {
            $projectNamespaces = Config::getInstance()->getSetupGacela()->getProjectNamespaces();
        } catch (Throwable) {
            return [];
        }

        $projectNamespaces[] = $classInfo->getModuleNamespace();

        $rules = [
            new FinderRuleWithModulePrefix(),
            new FinderRuleWithoutModulePrefix(),
        ];

        $candidates = [];
        foreach ($projectNamespaces as $projectNamespace) {
            foreach ($rules as $rule) {
                $candidates[] = $rule->buildClassCandidate($projectNamespace, $resolvableType, $classInfo);
            }
        }

        return array_values(array_unique($candidates));
    }

    private function findClassNameExample(ClassInfo $classInfo, string $resolvableType): string
    {
        return sprintf(
            '\\%s\\%s\\%s',
            $classInfo->getModuleNamespace(),
            $classInfo->getModuleName(),
            $resolvableType,
        );
    }
}
