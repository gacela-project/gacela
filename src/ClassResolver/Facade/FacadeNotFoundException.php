<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\Facade;

use Exception;
use Gacela\ClassResolver\ClassInfo;
use Gacela\ClassResolver\ClassNameFinder\ClassNameFinder;
use Gacela\Exception\Backtrace;

final class FacadeNotFoundException extends Exception
{
    public function __construct(ClassInfo $callerClassInfo)
    {
        parent::__construct($this->buildMessage($callerClassInfo));
    }

    private function buildMessage(ClassInfo $callerClassInfo): string
    {
        $message = 'ClassResolver Exception' . PHP_EOL;
        $message .= sprintf(
            'Cannot resolve `%1$sFacade` in your module `%1$s`',
            $callerClassInfo->getModule()
        ) . PHP_EOL;

        $message .= 'You can fix this by adding the missing Facade to your module.' . PHP_EOL;

        $message .= sprintf(
            'E.g. %s',
            (string)(new ClassNameFinder())->findClassName($callerClassInfo, 'Facade')
        ) . PHP_EOL;

        return $message . Backtrace::get();
    }
}
