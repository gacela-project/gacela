<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\DocumentationGenerator;

use Gacela\Console\Domain\AllAppModules\AppModule;
use ReflectionClass;
use ReflectionMethod;

use ReflectionType;

use function implode;
use function sprintf;

final class DocumentationGenerator
{
    /**
     * @param list<AppModule> $modules
     * @param list<array{from: string, to: string}> $dependencies
     */
    public function generateModuleDocumentation(AppModule $module, array $dependencies): string
    {
        $moduleName = $module->moduleName();
        $facadeClass = $module->facadeClass();

        $doc = "# {$moduleName} Module\n\n";
        $doc .= "## Overview\n\n";
        $doc .= "Facade: `{$facadeClass}`\n\n";

        // Add module structure
        $doc .= "## Module Structure\n\n";
        $doc .= $this->generateModuleStructure($module);

        // Add public methods
        $doc .= "\n## Public Methods\n\n";
        $doc .= $this->generatePublicMethods($module);

        // Add dependencies
        if ($dependencies !== []) {
            $doc .= "\n## Dependencies\n\n";
            $doc .= $this->generateDependencies($dependencies);
        }

        // Add usage example
        $doc .= "\n## Usage Example\n\n";

        return $doc . $this->generateUsageExample($module);
    }

    private function generateModuleStructure(AppModule $module): string
    {
        $structure = "```\n";
        $structure .= sprintf("├── %s (Facade)\n", $this->getClassName($module->facadeClass()));

        if ($module->factoryClass() !== null) {
            $structure .= sprintf("├── %s (Factory)\n", $this->getClassName($module->factoryClass()));
        }

        if ($module->configClass() !== null) {
            $structure .= sprintf("├── %s (Config)\n", $this->getClassName($module->configClass()));
        }

        if ($module->providerClass() !== null) {
            $structure .= sprintf("└── %s (Provider)\n", $this->getClassName($module->providerClass()));
        }

        return $structure . "```\n";
    }

    private function generatePublicMethods(AppModule $module): string
    {
        $facadeClass = $module->facadeClass();

        if (!class_exists($facadeClass)) {
            return "*No public methods available*\n";
        }

        $reflection = new ReflectionClass($facadeClass);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        $doc = '';

        foreach ($methods as $method) {
            // Skip inherited methods from AbstractFacade
            if ($method->getDeclaringClass()->getName() !== $facadeClass) {
                continue;
            }

            $methodName = $method->getName();
            $params = [];

            foreach ($method->getParameters() as $param) {
                $paramType = $param->getType() instanceof ReflectionType ? $param->getType() . ' ' : '';
                $params[] = sprintf('%s$%s', $paramType, $param->getName());
            }

            $returnType = $method->getReturnType() instanceof ReflectionType ? ': ' . $method->getReturnType() : '';
            $signature = sprintf('%s(%s)%s', $methodName, implode(', ', $params), $returnType);

            $doc .= sprintf("### `%s`\n\n", $signature);

            $docComment = $method->getDocComment();
            if ($docComment !== false) {
                $doc .= $this->extractDocDescription($docComment) . "\n\n";
            }
        }

        return $doc !== '' ? $doc : "*No public methods available*\n";
    }

    /**
     * @param list<array{from: string, to: string}> $dependencies
     */
    private function generateDependencies(array $dependencies): string
    {
        $doc = "This module depends on:\n\n";

        foreach ($dependencies as $dep) {
            $doc .= sprintf("- `%s`\n", $dep['to']);
        }

        return $doc . "\n";
    }

    private function generateUsageExample(AppModule $module): string
    {
        $facadeClass = $module->facadeClass();
        $className = $this->getClassName($facadeClass);

        return <<<MD
```php
<?php

use {$facadeClass};

\$facade = new {$className}();
// Use facade methods here
```

MD;
    }

    /**
     * @param class-string $className
     */
    private function getClassName(string $className): string
    {
        $parts = explode('\\', $className);

        return end($parts) ?: $className;
    }

    private function extractDocDescription(string $docComment): string
    {
        $lines = explode("\n", $docComment);
        $description = [];

        foreach ($lines as $line) {
            $line = trim($line, " \t/*");
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '@')) {
                continue;
            }

            $description[] = $line;
        }

        return implode(' ', $description);
    }
}
