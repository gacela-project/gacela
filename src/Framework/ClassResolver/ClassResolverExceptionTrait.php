<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function sprintf;

trait ClassResolverExceptionTrait
{
    /**
     * @param object|class-string $caller
     */
    private function buildMessage(object|string $caller, string $resolvableType): string
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
            $resolvableType,
        ) . PHP_EOL;

        return $message . (sprintf(
            'E.g. `%s`',
            $this->findClassNameExample($callerClassInfo, $resolvableType),
        ) . PHP_EOL);
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
