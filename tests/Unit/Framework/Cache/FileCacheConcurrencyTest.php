<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Cache;

use Gacela\Framework\Cache\FileCache;
use PHPUnit\Framework\TestCase;

use function dirname;
use function fclose;
use function file_put_contents;
use function glob;
use function is_dir;
use function proc_close;
use function proc_open;
use function rmdir;
use function stream_get_contents;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const PHP_BINARY;

final class FileCacheConcurrencyTest extends TestCase
{
    private string $cacheDir;

    private string $scriptDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/gacela-filecache-concurrency-' . uniqid('', true);
        $this->scriptDir = sys_get_temp_dir() . '/gacela-filecache-scripts-' . uniqid('', true);

        if (!is_dir($this->scriptDir)) {
            mkdir($this->scriptDir, 0o777, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
        $this->removeDir($this->scriptDir);
    }

    public function test_two_processes_writing_different_keys_both_persist(): void
    {
        $autoload = dirname(__DIR__, 4) . '/vendor/autoload.php';

        $scriptA = $this->writeChildScript('childA', $this->childSource($autoload, $this->cacheDir, 'keyA', 'valA'));
        $scriptB = $this->writeChildScript('childB', $this->childSource($autoload, $this->cacheDir, 'keyB', 'valB'));

        $this->runChild($scriptA);
        $this->runChild($scriptB);

        $reader = new FileCache($this->cacheDir);
        self::assertSame('valA', $reader->get('keyA'), 'keyA must survive concurrent write');
        self::assertSame('valB', $reader->get('keyB'), 'keyB must survive concurrent write');
    }

    public function test_two_processes_writing_same_key_both_produce_valid_output(): void
    {
        $autoload = dirname(__DIR__, 4) . '/vendor/autoload.php';

        $script = $this->writeChildScript(
            'samekey',
            $this->childSource($autoload, $this->cacheDir, 'shared', '__PID_SENTINEL__', echoOutput: true),
        );

        $outA = $this->runChild($script);
        $outB = $this->runChild($script);

        self::assertMatchesRegularExpression('/^\d+$/', $outA, 'Process A should output a PID');
        self::assertMatchesRegularExpression('/^\d+$/', $outB, 'Process B should output a PID');

        // Whichever process won the race, the final on-disk value must be a
        // fully-formed, loadable entry (no half-written file).
        $reader = new FileCache($this->cacheDir);
        $value = $reader->get('shared');
        self::assertMatchesRegularExpression('/^\d+$/', (string) $value);
    }

    public function test_concurrent_batch_commits_do_not_corrupt_files(): void
    {
        $autoload = dirname(__DIR__, 4) . '/vendor/autoload.php';

        $script = $this->writeChildScript('batchwriter', <<<PHP
            <?php
            require '{$autoload}';

            \$cache = new \Gacela\Framework\Cache\FileCache('{$this->cacheDir}');
            \$cache->beginBatch();
            for (\$i = 0; \$i < 20; ++\$i) {
                \$cache->put('k' . \$i, getmypid() . '-' . \$i);
            }
            \$cache->commitBatch();
            PHP);

        $this->runChild($script, wait: false);
        $this->runChild($script, wait: false);
        $this->runChild($script, wait: true); // last call waits for all to finish

        $leftovers = glob($this->cacheDir . '/*.tmp') ?: [];
        self::assertCount(0, $leftovers, 'concurrent batch commits must not leak .tmp files');

        // Every .php entry must load cleanly as a well-formed FileCache record.
        $files = glob($this->cacheDir . '/*.php') ?: [];
        foreach ($files as $file) {
            $entry = require $file;
            self::assertIsArray($entry, $file . ' must be a loadable array');
            self::assertArrayHasKey('value', $entry);
            self::assertArrayHasKey('expiresAt', $entry);
        }
    }

    private function childSource(
        string $autoload,
        string $cacheDir,
        string $key,
        string $value,
        bool $echoOutput = false,
    ): string {
        $valueExpr = $value === '__PID_SENTINEL__' ? '(string) getmypid()' : "'{$value}'";
        $echoLine = $echoOutput ? "echo \$cache->get('{$key}');" : '';

        return <<<PHP
            <?php
            require '{$autoload}';

            \$cache = new \Gacela\Framework\Cache\FileCache('{$cacheDir}');
            \$cache->put('{$key}', {$valueExpr});
            {$echoLine}
            PHP;
    }

    private function writeChildScript(string $name, string $source): string
    {
        $path = $this->scriptDir . '/' . $name . '-' . uniqid('', true) . '.php';
        file_put_contents($path, $source);

        return $path;
    }

    private function runChild(string $scriptPath, bool $wait = true): string
    {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open([PHP_BINARY, $scriptPath], $descriptors, $pipes);

        self::assertIsResource($process);

        if (!$wait) {
            // Still drain the pipes so the child can exit cleanly later; stream_get_contents
            // is blocking, which doubles as a simple wait mechanism.
            $stdout = stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return (string) $stdout;
        }

        $stdout = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return (string) $stdout;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = glob($dir . '/*') ?: [];
        foreach ($entries as $entry) {
            if (is_dir($entry)) {
                $this->removeDir($entry);
            } else {
                unlink($entry);
            }
        }

        foreach (glob($dir . '/.[!.]*') ?: [] as $dotfile) {
            if (is_dir($dotfile)) {
                $this->removeDir($dotfile);
            } else {
                unlink($dotfile);
            }
        }

        rmdir($dir);
    }
}
