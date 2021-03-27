<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\Config;

use Exception;
use Gacela\ClassResolver\ClassInfo;
use Gacela\ClassResolver\ClassResolverFactory;
use Gacela\Exception\Backtrace;

final class ConfigNotFoundException extends Exception
{
    public function __construct(ClassInfo $callerClassInfo)
    {
        parent::__construct($this->buildMessage($callerClassInfo));
    }

    private function buildMessage(ClassInfo $callerClassInfo): string
    {
        $message = 'ClassResolver Exception' . PHP_EOL;
        $message .= sprintf(
            'Cannot resolve %1$sConfig for your module "%1$s"',
            $callerClassInfo->getModule()
        ) . PHP_EOL;

        $message .= 'You can fix this by adding the missing Config to your module.' . PHP_EOL;

        $message .= sprintf(
            'E.g. %s',
            $this->findClassNameExample($callerClassInfo, 'Config')
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
