<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\Rules\CacheableWithoutCachedCallAnalyser;

/**
 * @see CacheableWithoutCachedCallAnalyser for what is checked and why
 */
final class CacheableWithoutCachedCallRule extends InClassAnalyserRule
{
    public function __construct()
    {
        parent::__construct(new CacheableWithoutCachedCallAnalyser());
    }
}
