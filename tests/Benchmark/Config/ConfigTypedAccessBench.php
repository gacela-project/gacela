<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use PhpBench\Attributes\Assert;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

/**
 * Tracks the runtime cost of the typed config accessors against the raw
 * get()+cast they replace (bench_get_int_raw_cast is the documented
 * comparison witness). The typed getters are self-contained (single
 * array_key_exists, null-default compare, no get() delegation) and should
 * stay at least as fast as get()+cast.
 *
 * Informational, not gated: these subjects run at ~0.06-0.20μs, where timer
 * quantization alone moves the mode by ~10% between runs, so a strict +/-10%
 * baseline assert false-fails unrelated PRs. The class-level assertion widens
 * the tolerance so the numbers are still reported and compared, but noise can't
 * break CI. See tests/Benchmark/README.md.
 *
 * @BeforeMethods("setUp")
 */
#[Assert('mode(variant.time.avg) <= mode(baseline.time.avg) +/- 1000%')]
#[Groups(['micro', 'config'])]
#[Revs(1000)]
#[Iterations(5)]
final class ConfigTypedAccessBench
{
    private Config $config;

    public function setUp(): void
    {
        Config::resetInstance();

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
            $config->addAppConfigKeyValues([
                'a-string' => 'hello',
                'an-int' => 42,
                'a-float' => 3.14,
                'a-bool' => true,
                'an-array' => ['x' => 1],
            ]);
        });

        $this->config = Config::getInstance();
    }

    public function bench_get_int_raw_cast(): void
    {
        $value = (int) $this->config->get('an-int');
    }

    public function bench_get_int_typed(): void
    {
        $value = $this->config->getInt('an-int');
    }

    public function bench_get_string_typed(): void
    {
        $value = $this->config->getString('a-string');
    }

    public function bench_get_bool_typed(): void
    {
        $value = $this->config->getBool('a-bool');
    }

    public function bench_get_array_typed(): void
    {
        $value = $this->config->getArray('an-array');
    }

    public function bench_get_float_typed(): void
    {
        $value = $this->config->getFloat('a-float');
    }
}
