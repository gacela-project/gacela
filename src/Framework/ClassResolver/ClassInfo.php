<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function array_slice;
use function count;
use function get_class;
use function is_string;

final class ClassInfo
{
    public const MODULE_NAME_ANONYMOUS = 'module-name@anonymous';

    private ?string $cacheKey = null;
    private string $callerModuleName;
    private string $callerNamespace;

    public function __construct(string $callerNamespace, string $callerModuleName)
    {
        $this->callerNamespace = $callerNamespace;
        $this->callerModuleName = $callerModuleName;
    }

    public static function fromObject(object $callerObject): self
    {
        $callerClass = get_class($callerObject);

        /** @var string[] $callerClassParts */
        $callerClassParts = explode('\\', ltrim($callerClass, '\\'));
        $lastCallerClassPart = end($callerClassParts);
        $filepath = is_string($lastCallerClassPart) ? $lastCallerClassPart : '';
        $filename = self::normalizeFilename($filepath);

        if (false !== strpos($filepath, 'anonymous')) {
            $callerClassParts = [
                self::MODULE_NAME_ANONYMOUS . '\\' . $filename,
                $filepath,
            ];
        }

        $callerNamespace = implode('\\', array_slice($callerClassParts, 0, count($callerClassParts) - 1));
        $callerModuleName = $callerClassParts[count($callerClassParts) - 2] ?? '';

        return new self($callerNamespace, $callerModuleName);
    }

    private static function normalizeFilename(string $filepath): string
    {
        $filename = basename($filepath);
        $filename = substr($filename, 0, (int)strpos($filename, ':'));

        if (false === ($pos = strpos($filename, '.'))) {
            return $filename;
        }

        return substr($filename, 0, $pos);
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

    public function toString(): string
    {
        return sprintf(
            'ClassInfo{$cacheKey:%s, $callerModuleName:%s, $callerNamespace:%s}',
            $this->cacheKey ?? 'null',
            $this->callerModuleName,
            $this->callerNamespace,
        );
    }
}
