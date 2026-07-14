<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures;

use function error_reporting;
use function restore_error_handler;
use function set_error_handler;

use const E_WARNING;

trait WarningCollectorTrait
{
    /**
     * Run $probe and return the user-visible warnings it raised.
     *
     * @param mixed $result receives $probe's return value
     *
     * @return list<string>
     */
    private function collectWarnings(callable $probe, mixed &$result = null): array
    {
        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            // @-suppressed warnings still reach custom handlers; mirror the
            // engine's suppression check so only user-visible warnings count.
            if ((error_reporting() & $errno) !== 0) {
                $warnings[] = $errstr;
            }

            return true;
        }, E_WARNING);

        try {
            $result = $probe();
        } finally {
            restore_error_handler();
        }

        return $warnings;
    }
}
