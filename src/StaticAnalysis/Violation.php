<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

use PhpParser\Node;

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
     * @param string      $identifier stable, dot-separated, e.g. `gacela.suffixExtends`
     * @param string|null $tip        what to do about it. PHPStan renders this on its
     *                                own line; Psalm has no such channel, so it is
     *                                appended to the message there. A rule that can
     *                                name the correction should
     * @param Node|null   $node       the node the finding belongs to, when that is
     *                                not the one being analysed -- as with a method
     *                                reported from inside its class. A node rather
     *                                than a line number because Psalm locates an
     *                                issue by its exact source span, and a line
     *                                cannot be widened back into one
     */
    public function __construct(
        public readonly string $message,
        public readonly string $identifier,
        public readonly ?string $tip = null,
        public readonly ?Node $node = null,
    ) {
    }

    /**
     * The same finding, pinned to the node it belongs to.
     *
     * A rule that judges one member at a time does not carry the node itself --
     * it is handed one and reports on it -- so whoever ran the rule attaches it
     * before the finding leaves for a host.
     */
    public function at(Node $node): self
    {
        return new self($this->message, $this->identifier, $this->tip, $node);
    }

    /**
     * The message with the tip folded in, for hosts that have nowhere else to
     * put it.
     */
    public function messageWithTip(): string
    {
        return $this->tip === null ? $this->message : $this->message . ' ' . $this->tip;
    }
}
