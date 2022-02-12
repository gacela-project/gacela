<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function array_slice;
use function count;
use function get_class;

final class ClassInfo
{
    public const MODULE_NAME_ANONYMOUS = 'module-name@anonymous';

    private ?string $cacheKey = null;
    private string $callerModuleName;
    private string $callerNamespace;

    public function __construct(object $callerObject)
    {
        $callerClass = get_class($callerObject);

        /** @var string[] $callerClassParts */
        $callerClassParts = explode('\\', ltrim($callerClass, '\\'));
        $lastCallerClassPart = end($callerClassParts);
        $filepath = is_string($lastCallerClassPart) ? $lastCallerClassPart : '';
        $filename = $this->normalizeFilename($filepath);

        if (false !== strpos($filepath, 'anonymous')) {
            $callerClassParts = [
                self::MODULE_NAME_ANONYMOUS . '\\' . $filename,
                $filepath,
            ];
        }

        $this->callerNamespace = $this->normalizeCallerNamespace(...$callerClassParts);
        $this->callerModuleName = $this->normalizeCallerModuleName(...$callerClassParts);
    }

    public function getCacheKey(string $resolvableType): string
    {
        if (!$this->cacheKey) {
            $this->cacheKey = GlobalKey::fromClassName(sprintf(
                '\\%s\\%s',
                $this->getFullNamespace(),
                $resolvableType
            ));
        }

        return $this->cacheKey;
    }

    public function getModule(): string
    {
        return $this->callerModuleName;
    }

    public function getFullNamespace(): string
    {
        return $this->callerNamespace;
    }

    private function normalizeFilename(string $filepath): string
    {
        $filename = basename($filepath);
        $filename = substr($filename, 0, (int)strpos($filename, ':'));

        if (false === ($pos = strpos($filename, '.'))) {
            return $filename;
        }

        return substr($filename, 0, $pos);
    }

    private function normalizeCallerNamespace(string ...$callerClassParts): string
    {
        return implode('\\', array_slice($callerClassParts, 0, count($callerClassParts) - 1));
    }

    private function normalizeCallerModuleName(string ...$callerClassParts): string
    {
        return $callerClassParts[count($callerClassParts) - 2] ?? '';
    }

    public function copyWith1LevelUpNamespace(): ?self
    {
        $parentNamespace = $this->parentNamespace();
        if (empty($parentNamespace)) {
            return null;
        }

        $clone = clone $this;
        $clone->callerNamespace = $parentNamespace;
        $oldModuleName = trim(str_replace($parentNamespace, '', $this->callerNamespace), '\\');
        $clone->callerModuleName = $oldModuleName;

        if ($clone->cacheKey !== null) {
            $clone->cacheKey = str_replace($oldModuleName, '', (string)$this->cacheKey);
        }

        return $clone;
    }

    private function parentNamespace(): string
    {
        return $this->removeLastNamespace($this->callerNamespace);
    }

    private function removeLastNamespace(string $string): string
    {
        return substr($string, 0, (int)strrpos($string, '\\'));
    }

    /**
     * @internal
     */
    public function __toString(): string
    {
        return sprintf(
            'ClassInfo{$cacheKey:%s, $callerModuleName:%s, $callerNamespace:%s}',
            $this->cacheKey ?? 'null',
            $this->callerModuleName,
            $this->callerNamespace,
        );
    }
}
