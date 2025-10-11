<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\PathNormalizer;

use Override;

use function sprintf;

final class WithSuffixAbsolutePathStrategy implements AbsolutePathStrategyInterface
{
    public function __construct(
        private readonly string $appRootDir,
        private readonly string $configFileNameSuffix = '',
    ) {
    }

    #[Override]
    public function generateAbsolutePath(string $relativePath): string
    {
        $suffix = $this->configFileNameSuffix;
        if ($suffix === '') {
            return '';
        }

        // place the file suffix right before the file extension
        $dotPos = strpos($relativePath, '.');

        if ($dotPos !== false) {
            $relativePathWithFileSuffix = substr($relativePath, 0, $dotPos)
                . '-' . $suffix
                . substr($relativePath, $dotPos);
        } else {
            $relativePathWithFileSuffix = $relativePath . '-' . $suffix;
        }

        return sprintf(
            '%s/%s',
            rtrim($this->appRootDir, '/'),
            ltrim($relativePathWithFileSuffix, '/'),
        );
    }
}
