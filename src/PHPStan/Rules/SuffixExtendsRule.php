<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\Rules\SuffixExtendsAnalyser;

/**
 * @see SuffixExtendsAnalyser for what is checked and why
 */
final class SuffixExtendsRule extends InClassAnalyserRule
{
    public function __construct(string $suffix, string $expectedParent)
    {
        parent::__construct(new SuffixExtendsAnalyser($suffix, $expectedParent));
    }
}
