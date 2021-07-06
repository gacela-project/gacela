<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\Exception\Backtrace;

trait ClassResolverExceptionTrait
{
    private function buildMessage(object $callerClass, string $resolvableType): string
    {
        $callerClassInfo = new ClassInfo($callerClass);

        $message = 'ClassResolver Exception' . PHP_EOL;
        $message .= sprintf(
            'Cannot resolve the "%s" for your module "%s"',
            $resolvableType,
            $callerClassInfo->getModule(),
        ) . PHP_EOL;

        $message .= sprintf(
            'You can fix this by adding the missing %s to your module.',
            $resolvableType
        ) . PHP_EOL;

        $message .= sprintf(
            'E.g. %s',
            $this->findClassNameExample($callerClassInfo, $resolvableType)
        ) . PHP_EOL;

        return $message . Backtrace::get();
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
