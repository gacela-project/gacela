<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\Deprecation;

use Gacela\Framework\Attribute\Deprecated;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use SplFileInfo;

use Throwable;

use function class_exists;

final readonly class DeprecationScanner
{
    /**
     * @param non-empty-string $rootPath
     */
    public function __construct(
        private string $rootPath,
    ) {
    }

    /**
     * @return list<TDeprecationInfo>
     */
    public function scan(): array
    {
        $deprecations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $pathname = $file->getPathname();

            // Skip vendor and test directories
            if (str_contains($pathname, '/vendor/') || str_contains($pathname, '/tests/')) {
                continue;
            }

            $deprecations = [
                ...$deprecations,
                ...$this->scanFile($pathname),
            ];
        }

        return $deprecations;
    }

    /**
     * @param non-empty-string $filePath
     *
     * @return list<TDeprecationInfo>
     */
    private function scanFile(string $filePath): array
    {
        $deprecations = [];

        // Extract namespace and class from file
        $content = (string)file_get_contents($filePath);

        // Simple regex to find namespace and class names
        if (!preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
            return [];
        }

        preg_match_all('/^(?:abstract\s+)?(?:final\s+)?(?:class|interface|trait|enum)\s+(\w+)/m', $content, $classMatches);

        if (!isset($classMatches[1]) || $classMatches[1] === []) {
            return [];
        }

        $namespace = $namespaceMatches[1];

        foreach ($classMatches[1] as $className) {
            $fullClassName = $namespace . '\\' . $className;

            if (!class_exists($fullClassName, false)) {
                // Try to load the class
                require_once $filePath;
            }

            if (!class_exists($fullClassName) && !interface_exists($fullClassName) && !trait_exists($fullClassName)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($fullClassName);

                // Check class deprecation
                $deprecations = [
                    ...$deprecations,
                    ...$this->extractDeprecationsFromAttributes(
                        $reflection->getAttributes(Deprecated::class),
                        $fullClassName,
                        'class',
                        $filePath,
                        $reflection->getStartLine() ?: 0,
                    ),
                ];

                // Check methods
                foreach ($reflection->getMethods() as $method) {
                    $deprecations = [
                        ...$deprecations,
                        ...$this->extractDeprecationsFromAttributes(
                            $method->getAttributes(Deprecated::class),
                            $fullClassName . '::' . $method->getName() . '()',
                            'method',
                            $filePath,
                            $method->getStartLine() ?: 0,
                        ),
                    ];
                }

                // Check properties
                foreach ($reflection->getProperties() as $property) {
                    $deprecations = [
                        ...$deprecations,
                        ...$this->extractDeprecationsFromAttributes(
                            $property->getAttributes(Deprecated::class),
                            $fullClassName . '::$' . $property->getName(),
                            'property',
                            $filePath,
                            0,
                        ),
                    ];
                }

                // Check constants
                foreach ($reflection->getReflectionConstants() as $constant) {
                    $deprecations = [
                        ...$deprecations,
                        ...$this->extractDeprecationsFromAttributes(
                            $constant->getAttributes(Deprecated::class),
                            $fullClassName . '::' . $constant->getName(),
                            'constant',
                            $filePath,
                            0,
                        ),
                    ];
                }
            } catch (Throwable) {
                // Skip classes that can't be reflected
                continue;
            }
        }

        return $deprecations;
    }

    /**
     * @param list<ReflectionAttribute<Deprecated>> $attributes
     * @param non-empty-string $elementName
     * @param non-empty-string $elementType
     * @param non-empty-string $file
     *
     * @return list<TDeprecationInfo>
     */
    private function extractDeprecationsFromAttributes(
        array $attributes,
        string $elementName,
        string $elementType,
        string $file,
        int $line,
    ): array {
        $deprecations = [];

        foreach ($attributes as $attribute) {
            $deprecated = $attribute->newInstance();

            $deprecations[] = new TDeprecationInfo(
                elementName: $elementName,
                elementType: $elementType,
                since: $deprecated->since,
                replacement: $deprecated->replacement,
                willRemoveIn: $deprecated->willRemoveIn,
                reason: $deprecated->reason,
                file: $file,
                line: $line,
            );
        }

        return $deprecations;
    }
}
