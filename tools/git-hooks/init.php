<?php

declare(strict_types=1);

// Skip on CI: hooks only help devs running `git commit` locally.
if (getenv('CI') !== false) {
    exit(0);
}

$cwd = getcwd();
if ($cwd === false) {
    fwrite(STDERR, "Cannot determine working directory\n");
    exit(1);
}

$gitDir = $cwd . DIRECTORY_SEPARATOR . '.git';
if (!is_dir($gitDir)) {
    // Not a git checkout (e.g. installed as a dependency): nothing to do.
    exit(0);
}

$hooksDir = $gitDir . DIRECTORY_SEPARATOR . 'hooks';
if (!is_dir($hooksDir) && !mkdir($hooksDir, 0o755, true) && !is_dir($hooksDir)) {
    fwrite(STDERR, "Cannot create hooks directory: {$hooksDir}\n");
    exit(1);
}

$source = $cwd . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'git-hooks' . DIRECTORY_SEPARATOR . 'pre-commit.sh';
$target = $hooksDir . DIRECTORY_SEPARATOR . 'pre-commit';

if (!is_file($source)) {
    fwrite(STDERR, "Pre-commit source not found: {$source}\n");
    exit(1);
}

echo "Initialising git hooks...\n";

if (file_exists($target) || is_link($target)) {
    @unlink($target);
}

$isWindows = DIRECTORY_SEPARATOR === '\\';
$installed = false;

if (!$isWindows && \function_exists('symlink')) {
    $installed = @symlink($source, $target);
}

if (!$installed) {
    $installed = @copy($source, $target);
}

if (!$installed) {
    fwrite(STDERR, "Failed to install pre-commit hook at {$target}\n");
    exit(1);
}

if (!$isWindows) {
    @chmod($target, 0o755);
}

echo "Done\n";
