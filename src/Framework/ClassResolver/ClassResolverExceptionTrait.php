<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\DocBlockService\CustomServicesProfiler;

trait ClassResolverExceptionTrait
{
    /**
     * @param object|class-string $caller
     */
    private function buildMessage($caller, string $resolvableType): string
    {
        $callerClassInfo = ClassInfo::from($caller, $resolvableType);

        $message = 'ClassResolver Exception' . PHP_EOL;
        $message .= sprintf(
            'Cannot resolve the `%s` for your module `%s`',
            $resolvableType,
            $callerClassInfo->getModuleName(),
        ) . PHP_EOL;

        $message .= sprintf(
            'You can fix this by adding the missing `%s` to your module.',
            $resolvableType
        ) . PHP_EOL;

        $message .= sprintf(
            'E.g. `%s`',
            $this->findClassNameExample($callerClassInfo, $resolvableType)
        ) . PHP_EOL;

        $message .= sprintf(
            'If you got this ↑ already, then try removing the cache files: `%s`, `%s`',
            ClassNameProfiler::CACHE_FILENAME,
            CustomServicesProfiler::CACHE_FILENAME,
        ) . PHP_EOL;

        return $message;
    }

    private function findClassNameExample(ClassInfo $classInfo, string $resolvableType): string
    {
        return sprintf(
            '\\%s\\%s',
            $classInfo->getModuleNamespace(),
            $resolvableType
        );
    }
}
