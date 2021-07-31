<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function ltrim;
use function preg_match;
use function sprintf;
use function strlen;
use function strpos;

final class GlobalKey
{
    private const RESOLVABLE_TYPES =  [
        'Facade',
        'Factory',
        'Config',
        'DependencyProvider',
    ];

    public static function fromClassName(string $fullClassName): string
    {
        preg_match('~(?<pre_namespace>.*)\\\(?<resolvable_type>.*)~', $fullClassName, $matches);
        $matchResolvableType = $matches['resolvable_type'] ?? '';

        ['module_name' => $moduleName, 'resolvable_type' => $resolvableType]
            = self::splitModuleAndResolvableType($matchResolvableType);

        if (empty($moduleName)) {
            return sprintf('\\%s', ltrim($fullClassName, '\\'));
        }

        return sprintf('\\%s\\%s', ltrim($matches['pre_namespace'], '\\'), $resolvableType);
    }

    /**
     * @return array{module_name:string, resolvable_type:string}
     */
    private static function splitModuleAndResolvableType(string $className): array
    {
        foreach (self::RESOLVABLE_TYPES as $resolvableType) {
            if (false !== strpos($className, $resolvableType)) {
                return [
                    'module_name' => substr($className, 0, strlen($className) - strlen($resolvableType)),
                    'resolvable_type' => $resolvableType,
                ];
            }
        }

        return [
            'module_name' => $className,
            'resolvable_type' => '',
        ];
    }
}
