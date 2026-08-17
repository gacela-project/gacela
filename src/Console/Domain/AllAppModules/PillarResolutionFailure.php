<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

use Throwable;

/**
 * Why a pillar resolved to nothing, when something was thrown saying so.
 *
 * {@see AppModuleCreator} has to answer `null` for a pillar it could not
 * resolve: `list:modules` prints a blank cell and `debug:module` says
 * `(not found)`, and both are right -- there is no Factory to name. What it used
 * to do as well was discard the reason, leaving every caller unable to tell "no
 * such pillar" from "the pillar failed to build". `doctor` then filled the gap
 * by guessing, and told readers whose namespace was perfectly correct to go and
 * check their namespace (#884, #890).
 *
 * The class and the message rather than the `Throwable`: this is carried around
 * with an `AppModule` for as long as the command runs, and nothing downstream
 * has any use for a stack trace it will never print.
 */
final class PillarResolutionFailure
{
    private function __construct(
        public readonly string $exceptionClass,
        public readonly string $message,
    ) {
    }

    public static function from(Throwable $throwable): self
    {
        return new self($throwable::class, $throwable->getMessage());
    }
}
