<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Validate;

use Gacela\Console\Application\Doctor\CheckStatus;

use function sprintf;
use function trim;

/**
 * One thing `validate:config` has to say.
 *
 * The validators used to write straight to the output and return
 * `array{errors: bool, warnings: bool}`, so the messages *were* the report --
 * there was nothing to hand a `--json` consumer but the prose, and a CI job
 * wanting to know which binding failed had to grep for it. A finding carries
 * the same sentence as data, and the renderers decide how to say it.
 *
 * `symbol` and `style` live here rather than being derived from `status`
 * because the console output they reproduce is not uniform: a note has no
 * symbol, an informational line is neither a pass nor a problem, and the
 * catch-all binding error prints without a cross. Deriving them would have
 * meant changing lines this refactor exists to leave alone.
 */
final class ValidationFinding
{
    /**
     * @param list<string> $details
     */
    private function __construct(
        public readonly CheckStatus $status,
        public readonly string $label,
        public readonly string $subject,
        public readonly array $details,
        public readonly string $symbol,
        public readonly string $style,
    ) {
    }

    /**
     * @param list<string> $details
     */
    public static function error(string $label, string $subject = '', array $details = []): self
    {
        return new self(CheckStatus::Error, $label, $subject, $details, '✗', 'error');
    }

    /**
     * @param list<string> $details
     */
    public static function warning(string $label, string $subject = '', array $details = []): self
    {
        return new self(CheckStatus::Warn, $label, $subject, $details, '⚠', 'fg=yellow');
    }

    public static function ok(string $label, string $subject = ''): self
    {
        return new self(CheckStatus::Ok, $label, $subject, [], '✓', 'fg=green');
    }

    /**
     * Neither a pass nor a problem: something the run did not look at, named so
     * the reader does not read its absence as a clean bill of health.
     */
    public static function info(string $label, string $subject = ''): self
    {
        return new self(CheckStatus::Ok, $label, $subject, [], '•', 'fg=cyan');
    }

    /**
     * An observation about the run itself -- nothing configured, nothing
     * declared -- which is why it carries no symbol.
     */
    public static function note(string $label): self
    {
        return new self(CheckStatus::Ok, $label, '', [], '', 'fg=cyan');
    }

    /**
     * A failure that stopped a validator rather than one it found, which is why
     * it prints without a cross: it is not a verdict on a binding. The whole
     * sentence is the label, so the line is coloured whole as it always was.
     */
    public static function failure(string $message): self
    {
        return new self(CheckStatus::Error, $message, '', [], '', 'error');
    }

    /**
     * The finding as one sentence, for a consumer that has no use for the
     * symbol it would have been printed with.
     */
    public function message(): string
    {
        if ($this->subject === '') {
            return $this->label;
        }

        if ($this->label === '') {
            return $this->subject;
        }

        return sprintf('%s: %s', $this->label, $this->subject);
    }

    /**
     * The console line, reproducing what each validator used to write inline.
     *
     * Three shapes, because that is how many the command already had: a label
     * alone is wrapped whole, a label with a subject closes the markup after
     * the colon so only the label is coloured, and a subject alone is the
     * per-binding tick.
     */
    public function render(): string
    {
        if ($this->label === '') {
            return sprintf('  <%s>%s</> %s', $this->style, $this->symbol, $this->subject);
        }

        $head = trim(sprintf('%s %s', $this->symbol, $this->label));

        if ($this->subject === '') {
            return sprintf('  <%s>%s</>', $this->style, $head);
        }

        return sprintf('  <%s>%s:</> %s', $this->style, $head, $this->subject);
    }
}
