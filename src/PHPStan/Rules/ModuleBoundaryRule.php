<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function preg_match;
use function sprintf;
use function str_contains;

/**
 * Enforces module boundaries by preventing direct access to internal classes across modules.
 * Only allows facade access between modules.
 *
 * @implements Rule<Node\Expr>
 */
final class ModuleBoundaryRule implements Rule
{
    /**
     * @param list<string> $allowedPaths Paths that are allowed to access internal classes (e.g., ['tests/'])
     * @param list<string> $restrictedPaths Paths that should enforce boundaries (e.g., ['Domain/', 'Infrastructure/'])
     */
    public function __construct(private readonly array $allowedPaths = [], private readonly array $restrictedPaths = ['Domain', 'Infrastructure'])
    {
    }

    public function getNodeType(): string
    {
        return Node\Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof New_ && !$node instanceof StaticCall) {
            return [];
        }

        $callerClass = $scope->getClassReflection();
        if (!$callerClass instanceof \PHPStan\Reflection\ClassReflection) {
            return [];
        }

        $callerClassName = $callerClass->getName();
        $callerFile = $scope->getFile();

        // Skip if caller is in allowed paths (e.g., tests)
        foreach ($this->allowedPaths as $allowedPath) {
            if (str_contains($callerFile, $allowedPath)) {
                return [];
            }
        }

        $targetClassName = $this->extractClassName($node);
        if ($targetClassName === null) {
            return [];
        }

        // Check if crossing module boundary
        $callerModule = $this->extractModuleName($callerClassName);
        $targetModule = $this->extractModuleName($targetClassName);

        if ($callerModule === null || $targetModule === null || $callerModule === $targetModule) {
            return [];
        }

        // Check if accessing internal class (Domain/Infrastructure) from another module
        if ($this->isInternalClass($targetClassName) && !$this->isFacadeClass($targetClassName)) {
            return [
                RuleErrorBuilder::message(
                    sprintf(
                        'Module boundary violation: %s from module "%s" cannot directly access %s from module "%s". Use the module\'s Facade instead.',
                        $this->getClassType($callerClassName),
                        $callerModule,
                        $this->getClassType($targetClassName),
                        $targetModule,
                    ),
                )->build(),
            ];
        }

        return [];
    }

    private function extractClassName(Node\Expr $node): ?string
    {
        if ($node instanceof New_ && $node->class instanceof Name) {
            return $node->class->toString();
        }

        if ($node instanceof StaticCall && $node->class instanceof Name) {
            return $node->class->toString();
        }

        return null;
    }

    /**
     * Extract module name from class name.
     * Example: App\Module1\Domain\Service -> Module1
     */
    private function extractModuleName(string $className): ?string
    {
        // Match pattern: namespace segments ending with known Gacela components
        // Example patterns:
        // - Vendor\Module\Domain\Class
        // - Vendor\Module\Infrastructure\Class
        // - Vendor\Module\ModuleFacade
        // - Vendor\Module\ModuleFactory
        if (preg_match('/\\\\([^\\\\]+)\\\\(?:' . implode('|', $this->restrictedPaths) . '|(?:[^\\\\]+(?:Facade|Factory|Config|Provider)))/', $className, $matches) === 1) {
            return $matches[1];
        }

        // Also match if class itself is a Facade/Factory/Config/Provider
        if (preg_match('/\\\\([^\\\\]+)(?:Facade|Factory|Config|Provider)$/', $className, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function isInternalClass(string $className): bool
    {
        foreach ($this->restrictedPaths as $path) {
            if (str_contains($className, '\\' . $path . '\\')) {
                return true;
            }
        }

        return false;
    }

    private function isFacadeClass(string $className): bool
    {
        return str_ends_with($className, 'Facade');
    }

    private function getClassType(string $className): string
    {
        if (str_contains($className, '\\Domain\\')) {
            return 'Domain class';
        }

        if (str_contains($className, '\\Infrastructure\\')) {
            return 'Infrastructure class';
        }

        if (str_ends_with($className, 'Factory')) {
            return 'Factory';
        }

        if (str_ends_with($className, 'Config')) {
            return 'Config';
        }

        return 'class';
    }
}
