<?php

declare(strict_types=1);

function setup_git_hooks(): void
{
    echo "Initialising git hooks..." . PHP_EOL;

    $preCommitSource = __DIR__ . DIRECTORY_SEPARATOR . 'pre-commit.sh';
    $preCommitTarget = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'pre-commit';

    if (file_exists($preCommitTarget)) {
        unlink($preCommitTarget);
    }

    if (!@symlink($preCommitSource, $preCommitTarget)) {
        copy($preCommitSource, $preCommitTarget);
    }

    echo "Done" . PHP_EOL;
}

setup_git_hooks();

