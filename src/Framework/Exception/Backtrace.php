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

    /**
     * @param array<array-key, mixed> $backtrace
     */
    private function getTraceLine(array $backtrace): string
    {
        /** @var null|string $file */
        $file = $backtrace['file'];
        if (isset($file)) {
            /** @var string $line */
            $line = $backtrace['line'];
            return $file . ':' . $line;
        }

        return $this->getTraceLineFromTestCase($backtrace);
    }

    /**
     * @param array<array-key, mixed> $backtrace
     */
    private function getTraceLineFromTestCase(array $backtrace): string
    {
        /** @var string $class */
        $class = $backtrace['class'];
        /** @var string $type */
        $type = $backtrace['type'];
        /** @var string $function */
        $function = $backtrace['function'];

        return $class . $type . $function;
    }
}
