<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;

/**
 * Tracks the runtime cost of the typed config accessors against the raw
 * get()+cast they replace. The typed getters are self-contained (single
 * array_key_exists, null-default compare, no get() delegation) and should
 * stay at least as fast as get()+cast. The suite-wide +/-10% baseline assert
 * (phpbench.json) guards against future regressions.
 *
 * @BeforeMethods("setUp")
 */
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
