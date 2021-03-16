<?php

declare(strict_types=1);

namespace Gacela\Exception;

final class Backtrace
{
    private string $backtrace = '';

    public static function get(): string
    {
        return (new self())->backtrace;
    }

    private function __construct()
    {
        $backtraceCollection = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($backtraceCollection as $backtrace) {
            $this->backtrace .= $this->getTraceLine($backtrace) . PHP_EOL;
        }
    }

    private function getTraceLine(array $backtrace): string
    {
        if (isset($backtrace['file'])) {
            return $backtrace['file'] . ':' . $backtrace['line'];
        }

        return $this->getTraceLineFromTestCase($backtrace);
    }

    private function getTraceLineFromTestCase(array $backtrace): string
    {
        return $backtrace['class'] . $backtrace['type'] . $backtrace['function'];
    }
}
