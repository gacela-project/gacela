<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

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
            $callerClassInfo->getModule(),
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
            'If you got this ↑ already, then try removing the cache file: `%s`',
            ClassNameCache::CACHED_CLASS_NAMES_FILE
        ) . PHP_EOL;

        return $message;
    }

    private function findClassNameExample(ClassInfo $classInfo, string $resolvableType): string
    {
        return sprintf(
            '\\%s\\%s',
            $classInfo->getFullNamespace(),
            $resolvableType
        );
    }
}
