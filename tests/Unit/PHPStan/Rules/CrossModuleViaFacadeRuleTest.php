<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\PHPStan\Rules\CrossModuleViaFacadeRule;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPUnit\Framework\TestCase;

use function assert;
use function is_string;
use function sprintf;

final class CrossModuleViaFacadeRuleTest extends TestCase
{
    public function test_reports_cross_module_new_of_non_facade(): void
    {
        $errors = $this->runOn(
            currentClass: 'App\Modules\User\UserFactory',
            classSource: '
                final class UserFactory {
                    public function a(): void
                    {
                        new \App\Modules\Shop\Domain\ShopService();
                    }
                }
            ',
        );

        self::assertCount(1, $errors);
        self::assertStringContainsString('App\Modules\User\UserFactory', $errors[0]);
        self::assertStringContainsString('App\Modules\Shop\Domain\ShopService', $errors[0]);
        self::assertStringContainsString('App\Modules\Shop', $errors[0]);
    }

    public function test_allows_cross_module_facade_reference(): void
    {
        $errors = $this->runOn(
            currentClass: 'App\Modules\User\UserFactory',
            classSource: '
                final class UserFactory {
                    public function a(): object
                    {
                        return new \App\Modules\Shop\ShopFacade();
                    }
                }
            ',
        );

        self::assertSame([], $errors);
    }

    public function test_allows_same_module_reference(): void
    {
        $errors = $this->runOn(
            currentClass: 'App\Modules\User\UserFactory',
            classSource: '
                final class UserFactory {
                    public function a(): object
                    {
                        return new \App\Modules\User\Domain\UserService();
                    }
                }
            ',
        );

        self::assertSame([], $errors);
    }

    public function test_ignores_classes_outside_root_namespace(): void
    {
        $errors = $this->runOn(
            currentClass: 'App\Modules\User\UserFactory',
            classSource: '
                final class UserFactory {
                    public function a(): \DateTimeImmutable
                    {
                        return new \DateTimeImmutable();
                    }
                }
            ',
        );

        self::assertSame([], $errors);
    }

    public function test_skips_when_current_class_is_outside_root_namespace(): void
    {
        $errors = $this->runOn(
            currentClass: 'Vendor\Other\SomeClass',
            classSource: '
                final class SomeClass {
                    public function a(): void
                    {
                        new \App\Modules\Shop\Domain\ShopService();
                    }
                }
            ',
        );

        self::assertSame([], $errors);
    }

    public function test_reports_static_call_into_another_module(): void
    {
        $errors = $this->runOn(
            currentClass: 'App\Modules\User\UserFactory',
            classSource: '
                final class UserFactory {
                    public function a(): void
                    {
                        \App\Modules\Shop\Domain\ShopService::doStuff();
                    }
                }
            ',
        );

        self::assertCount(1, $errors);
    }

    public function test_reports_class_const_fetch_into_another_module(): void
    {
        $errors = $this->runOn(
            currentClass: 'App\Modules\User\UserFactory',
            classSource: '
                final class UserFactory {
                    public function a(): string
                    {
                        return \App\Modules\Shop\Domain\ShopService::class;
                    }
                }
            ',
        );

        self::assertCount(1, $errors);
    }

    public function test_deduplicates_repeated_violations(): void
    {
        $errors = $this->runOn(
            currentClass: 'App\Modules\User\UserFactory',
            classSource: '
                final class UserFactory {
                    public function a(): object
                    {
                        return new \App\Modules\Shop\Domain\ShopService();
                    }
                    public function b(): object
                    {
                        return new \App\Modules\Shop\Domain\ShopService();
                    }
                }
            ',
        );

        self::assertCount(1, $errors);
    }

    public function test_respects_module_path_segments_setting(): void
    {
        $errors = $this->runOn(
            currentClass: 'App\Modules\Admin\User\UserFactory',
            classSource: '
                final class UserFactory {
                    public function a(): object
                    {
                        return new \App\Modules\Admin\Shop\ShopService();
                    }
                }
            ',
            rootNamespace: 'App\Modules',
            modulePathSegments: 2,
        );

        self::assertCount(1, $errors);
    }

    public function test_same_first_segment_is_same_module_when_depth_is_one(): void
    {
        $errors = $this->runOn(
            currentClass: 'App\Modules\Admin\User\UserFactory',
            classSource: '
                final class UserFactory {
                    public function a(): object
                    {
                        return new \App\Modules\Admin\Shop\ShopService();
                    }
                }
            ',
            rootNamespace: 'App\Modules',
            modulePathSegments: 1,
        );

        self::assertSame([], $errors);
    }

    public function test_ignores_references_with_no_module_depth(): void
    {
        $errors = $this->runOn(
            currentClass: 'App\Modules\User\UserFactory',
            classSource: '
                final class UserFactory {
                    public function a(): object
                    {
                        return new \App\Modules\Other();
                    }
                }
            ',
        );

        self::assertSame([], $errors);
    }

    public function test_skips_when_class_reflection_is_null(): void
    {
        $rule = new CrossModuleViaFacadeRule('App\Modules');
        $classLike = $this->parseClass(
            '
            final class UserFactory {
                public function a(): void { new \App\Modules\Shop\Domain\ShopService(); }
            }
        ',
            'App\Modules\User',
        );
        $reflection = $this->createMock(ClassReflection::class);
        $inClassNode = new InClassNode($classLike, $reflection);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn(null);

        self::assertSame([], $rule->processNode($inClassNode, $scope));
    }

    public function test_get_node_type_returns_in_class_node(): void
    {
        $rule = new CrossModuleViaFacadeRule('App\Modules');
        self::assertSame(InClassNode::class, $rule->getNodeType());
    }

    /**
     * @return list<string>
     */
    private function runOn(
        string $currentClass,
        string $classSource,
        string $rootNamespace = 'App\Modules',
        int $modulePathSegments = 1,
    ): array {
        $pos = strrpos($currentClass, '\\');
        $namespace = $pos === false ? '' : substr($currentClass, 0, $pos);

        $classLike = $this->parseClass($classSource, $namespace);

        $reflection = $this->createMock(ClassReflection::class);
        $reflection->method('getName')->willReturn($currentClass);

        $inClassNode = new InClassNode($classLike, $reflection);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn($reflection);

        $rule = new CrossModuleViaFacadeRule($rootNamespace, $modulePathSegments);
        $errors = $rule->processNode($inClassNode, $scope);

        return array_map(
            static fn ($error): string => is_string($error) ? $error : $error->getMessage(),
            $errors,
        );
    }

    private function parseClass(string $classSource, string $namespace): ClassLike
    {
        $code = $namespace === ''
            ? sprintf("<?php\n%s\n", $classSource)
            : sprintf("<?php\nnamespace %s;\n%s\n", $namespace, $classSource);
        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse($code);
        assert($ast !== null);

        if ($namespace === '') {
            $classLike = $ast[0];
        } else {
            $ns = $ast[0];
            assert($ns instanceof Namespace_);
            $classLike = $ns->stmts[0];
        }

        assert($classLike instanceof ClassLike);

        return $classLike;
    }
}
