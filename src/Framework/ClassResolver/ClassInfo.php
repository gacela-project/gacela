<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function array_slice;
use function count;
use function get_class;

final class ClassInfo
{
    public const MODULE_NAME_ANONYMOUS = 'module-name@anonymous';

    private const MIN_LEVEL_NAMESPACE_TO_COPY = 2;

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

    public function toString(): string
    {
        return sprintf(
            'ClassInfo{$cacheKey:%s, $callerModuleName:%s, $callerNamespace:%s}',
            $this->cacheKey ?? 'null',
            $this->callerModuleName,
            $this->callerNamespace,
        );
    }

    public function copyWith1LevelUpNamespace(): ?self
    {
        try {
            $this->checkCanCopyWith1LevelUpNamespace();
        } catch (\RuntimeException $e) {
            // TODO: echo/log? $e->getMessage()
            return null;
        }

        $clone = clone $this;
        $lastPos = (int)strrpos($this->callerNamespace, '\\');
        $oldModuleName = substr($this->callerNamespace, $lastPos);
        $newCallerNamespace = str_replace($oldModuleName, '', $this->callerNamespace);

        $lastPos2 = (int)strrpos($newCallerNamespace, '\\');
        $newModuleName = substr($newCallerNamespace, $lastPos2);

        if ($clone->cacheKey !== null) {
            $clone->cacheKey = str_replace($oldModuleName, '', (string)$this->cacheKey);
        }
        $clone->callerNamespace = $newCallerNamespace;
        $clone->callerModuleName = ltrim($newModuleName, '\\');

        return $clone;
    }

    private function checkCanCopyWith1LevelUpNamespace(): void
    {
        /** @var array<int,int> $charsInBytes */
        $charsInBytes = \count_chars($this->callerNamespace, 1);
        $charsInBytesWithMinCount = \array_filter(
            $charsInBytes,
            static fn (int $v) => $v >= self::MIN_LEVEL_NAMESPACE_TO_COPY
        );
        $chars = \array_map(static fn ($k) => \chr($k), array_keys($charsInBytesWithMinCount));
        $chars2 = \array_flip($chars);
//        dd([
//            '$this->callerNamespace' => $this->callerNamespace,
//            '$charsInBytes' => $charsInBytes,
//            '$charsInBytesWithMinCount' => $charsInBytesWithMinCount,
//            '$chars' => $chars,
//            '$chars2' => $chars2,
//            ]);

        if (!isset($chars2['\\'])) {
            throw new \RuntimeException(sprintf(
                'Cannot copy classInfo with 1 level up namespace: %s',
                $this->callerNamespace
            ));
        }
    }
}
