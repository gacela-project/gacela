<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

/**
 * @codeCoverageIgnore
 */
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
            return ((string)$backtrace['file']) . ':' . ((string)$backtrace['line']);
        }

        return $this->getTraceLineFromTestCase($backtrace);
    }

    private function getTraceLineFromTestCase(array $backtrace): string
    {
        return ((string)$backtrace['class'])
            . ((string)$backtrace['type'])
            . ((string)$backtrace['function']);
    }
}
