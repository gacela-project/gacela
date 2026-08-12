<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Closure;
use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\IdeMeta\IdeMetadataPath;
use Gacela\Console\Domain\IdeMeta\IdeMetadataResult;

use function is_file;
use function sprintf;

/**
 * Reports generated editor metadata that no longer describes the application.
 *
 * Content is compared, not modification times: a Provider edited and reverted
 * leaves a newer file saying the same thing, and an mtime comparison would call
 * that stale forever.
 */
final class IdeMetadataStalenessCheck implements HealthCheck
{
    /**
     * One string rather than a concatenation: splitting it generates Concat
     * mutants that only a golden master over a sentence nobody reads character
     * by character could kill.
     */
    private const string STALE_DETAIL = '%s no longer matches the #[Provides] attributes';

    /**
     * @param Closure():IdeMetadataResult $regenerate re-derives the metadata without writing it
     */
    public function __construct(
        private readonly string $appRootDir,
        private readonly Closure $regenerate,
    ) {
    }

    public function name(): string
    {
        return 'IDE metadata';
    }

    public function run(): CheckResult
    {
        $path = IdeMetadataPath::fileIn($this->appRootDir);

        // Deliberately checked before regenerating: a project that never ran
        // `ide:meta` should not pay for an application-wide module scan to be
        // told about a file it does not have.
        if (!is_file($path)) {
            return CheckResult::ok($this->name(), 'no generated metadata — nothing to check');
        }

        if (!($this->regenerate)()->changed) {
            return CheckResult::ok($this->name(), 'matches the #[Provides] attributes');
        }

        return CheckResult::warn(
            $this->name(),
            [sprintf(self::STALE_DETAIL, $path)],
            'run `bin/gacela ide:meta` to regenerate',
        );
    }
}
