<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\PathNormalizer;

use function sprintf;
use function str_replace;
use function strpos;
use function strrpos;

final class WithSuffixAbsolutePathStrategy implements AbsolutePathStrategyInterface
{
    public function __construct(
        private readonly string $appRootDir,
        private readonly string $configFileNameSuffix = '',
    ) {
    }

    public function generateAbsolutePath(string $relativePath): string
    {
        $suffix = $this->configFileNameSuffix;
        if ($suffix === '') {
            return '';
        }

        // Before the first dot of the file name, never of a directory such as
        // `./` or `config.d/`: EnvironmentLayer strips it from the same place.
        $separatorPos = strrpos(str_replace('\\', '/', $relativePath), '/');
        $dotPos = strpos($relativePath, '.', $separatorPos === false ? 0 : $separatorPos + 1);

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
