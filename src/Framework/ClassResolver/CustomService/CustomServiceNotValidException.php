<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\CustomService;

use Exception;
use Gacela\Framework\AbstractCustomService;
use Gacela\Framework\ClassResolver\ClassInfo;

final class CustomServiceNotValidException extends Exception
{
    public function __construct(object $callerClass, string $resolvableType)
    {
        $callerClassInfo = new ClassInfo($callerClass);

        $message = 'ClassResolver Exception' . PHP_EOL;
        $message .= sprintf(
            'Is your custom service "%s" from your module "%s" extending %s?',
            $resolvableType,
            $callerClassInfo->getModule(),
            AbstractCustomService::class
        ) . PHP_EOL;

        parent::__construct($message);
    }
}
