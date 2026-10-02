<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function file_get_contents;
use function file_put_contents;
use function is_file;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function rtrim;
use function sprintf;

/**
 * Points the project's `AGENTS.md` at the guide Gacela ships for coding agents.
 *
 * A pointer rather than a copy: the guide lives in the installed package, so it
 * always describes the version the project runs, and upgrading Gacela updates
 * it with nothing to regenerate. The block sits between markers, so running
 * the command again rewrites only that block and nothing the project wrote.
 */
final class AgentsInstallCommand extends Command
{
    private const FILENAME = 'AGENTS.md';

    private const START = '<!-- gacela:start -->';

    private const END = '<!-- gacela:end -->';

    private const BLOCK = <<<'MD'
        <!-- gacela:start -->
        ## Gacela

        This project is split into Gacela modules. Before changing PHP code, read `vendor/gacela-project/gacela/resources/agents/gacela.md` and follow it. Check your work with `vendor/bin/gacela doctor --only-problems`.
        <!-- gacela:end -->
        MD;

    public function __construct(
        private readonly string $appRootDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('agents:install')
            ->setDescription("Point the project's AGENTS.md at the Gacela guide for coding agents");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $this->appRootDir . DIRECTORY_SEPARATOR . self::FILENAME;
        $current = is_file($target) ? file_get_contents($target) : null;

        // Read as empty, an unreadable file would be overwritten by the block.
        if ($current === false) {
            throw new RuntimeException(sprintf('File "%s" exists and could not be read', $target));
        }

        $next = $this->withBlock($current);

        if ($next === $current) {
            $output->writeln(sprintf('<fg=green>✓</> %s already points at the Gacela guide', self::FILENAME));

            return self::SUCCESS;
        }

        if (file_put_contents($target, $next) === false) {
            throw new RuntimeException(sprintf('File "%s" was not written', $target));
        }

        $output->writeln(sprintf(
            '<fg=green>✓</> %s %s',
            $current === null ? 'Created' : 'Updated',
            $target,
        ));

        return self::SUCCESS;
    }

    private function withBlock(?string $current): string
    {
        if ($current === null || rtrim($current) === '') {
            return self::BLOCK . "\n";
        }

        // A start marker, then an end marker with no other start between: a
        // stray marker left by hand never makes a match span the text after it.
        $start = preg_quote(self::START, '/');
        $pattern = '/' . $start . '(?:(?!' . $start . ').)*?' . preg_quote(self::END, '/') . '/s';

        if (preg_match($pattern, $current) === 1) {
            return (string) preg_replace($pattern, self::BLOCK, $current, 1);
        }

        return rtrim($current) . "\n\n" . self::BLOCK . "\n";
    }
}
