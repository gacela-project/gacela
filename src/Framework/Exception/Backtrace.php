<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

class Backtrace
{
    private string $backtrace = '';

    public function get(): string
    {
        foreach ($this->getBacktraces() as $backtrace) {
            $this->backtrace .= $this->getTraceLine($backtrace) . PHP_EOL;
        }

        return $this->backtrace;
    }

    /**
     * @param array{file:string, line:int} $backtrace
     */
    private function getTraceLine(array $backtrace): string
    {
        return $backtrace['file'] . ':' . $backtrace['line'];
    }

    /**
     * @return list<array{
     *     args?: list<mixed>,
     *     class?: class-string,
     *     file: string,
     *     function: string,
     *     line: int,
     *     object?: object,
     *     type?: string
     * }>
     *
     * @internal for testing purposes
     */
    public function getBacktraces(): array
    {
        return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);//@phpstan-ignore-line
    }
}
