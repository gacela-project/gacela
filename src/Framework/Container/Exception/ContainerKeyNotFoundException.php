<?php

declare(strict_types=1);

namespace Gacela\Framework\Container\Exception;

use Gacela\Framework\ClassResolver\ClassInfo;
use RuntimeException;

final class ContainerKeyNotFoundException extends RuntimeException
{
    public function __construct(object $caller, string $key)
    {
        $classInfo = ClassInfo::from($caller);

        parent::__construct($this->buildMessage($classInfo, $key));
    }

    protected function buildMessage(ClassInfo $callerClassInfo, string $key): string
    {
        $message = 'Container Exception' . PHP_EOL;
        $message .= "Container does not contain the called '{$key}'" . PHP_EOL;
        $message .= sprintf(
            'You can fix this by adding the key "%s" to your "%sDependencyProvider"',
            $key,
            $callerClassInfo->getModuleName(),
        );

        return $message;
    }
}
