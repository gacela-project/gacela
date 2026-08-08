<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

/**
 * One finding, in terms every host analyser can express.
 *
 * The identifier is not decoration: it is what a consumer suppresses on -- a
 * PHPStan `ignoreErrors` entry or a Psalm `<issueHandlers>` block -- so it is
 * part of the public contract of a rule and changing one is a breaking change.
 */
final class Violation
{
    /**
     * @param string   $identifier stable, dot-separated, e.g. `gacela.suffixExtends`
     * @param int|null $line       only when the finding belongs to a line other
     *                             than the analysed node's own, as with a method
     *                             reported inside its class
     */
    public function __construct(
        public readonly string $message,
        public readonly string $identifier,
        public readonly ?int $line = null,
    ) {
    }
}
