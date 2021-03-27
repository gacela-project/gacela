<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\Factory;

use Exception;
use Gacela\ClassResolver\ClassInfo;
use Gacela\ClassResolver\ClassResolverFactory;
use Gacela\Exception\Backtrace;

final class FactoryNotFoundException extends Exception
{
    public function __construct(ClassInfo $callerClassInfo)
    {
        parent::__construct($this->buildMessage($callerClassInfo));
    }

    private function buildMessage(ClassInfo $callerClassInfo): string
    {
        $message = 'ClassResolver Exception' . PHP_EOL;
        $message .= sprintf(
            'Cannot resolve %1$sFactory for your module "%1$s"',
            $callerClassInfo->getModule()
        ) . PHP_EOL;

        $message .= 'You can fix this by adding the missing Factory to your module.';

        $message .= sprintf(
            'E.g. %s',
            $this->findClassNameExample($callerClassInfo, 'Factory')
        ) . PHP_EOL;

        return $message . Backtrace::get();
    }

    private function findClassNameExample(ClassInfo $callerClassInfo, string $resolvableType): string
    {
        return (string)(new ClassResolverFactory())
            ->createClassNameFinder()
            ->findClassName($callerClassInfo, $resolvableType);
    }
}
