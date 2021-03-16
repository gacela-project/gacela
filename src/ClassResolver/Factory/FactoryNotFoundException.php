<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\Factory;

use Exception;
use Gacela\ClassResolver\ClassInfo;
use Gacela\ClassResolver\ClassResolverConfig;
use Gacela\Config;
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
            'E.g. %1$s\\%2$s\\%2$sFactory',
            Config::get(ClassResolverConfig::PROJECT_NAMESPACE),
            $callerClassInfo->getModule()
        ) . PHP_EOL;

        return $message . Backtrace::get();
    }
}
