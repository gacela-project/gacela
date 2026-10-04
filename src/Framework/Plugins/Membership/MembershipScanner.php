<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

use Closure;
use Gacela\Framework\Attribute\AsListener;
use Gacela\Framework\Attribute\Plugin;
use Gacela\Framework\Attribute\Tag;
use Gacela\Framework\Bootstrap\Package\PackageConfigFinder;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileInfo;

use function array_intersect;
use function array_keys;
use function array_map;
use function array_pop;
use function class_exists;
use function file_get_contents;
use function in_array;
use function is_dir;
use function ltrim;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strrchr;
use function substr;
use function trait_exists;
use function usort;

use const DIRECTORY_SEPARATOR;

/**
 * Finds the `#[Plugin]`, `#[Tag]` and `#[AsListener]` declarations of the
 * application by walking its module paths.
 *
 * A file is loaded only when it can name Gacela's attributes, imported or
 * written out, and declares a class inside `projectNamespaces`: a loose match
 * costs one class load, never a wrong member, because membership is read from
 * the attribute itself.
 *
 * @internal
 */
final class MembershipScanner
{
    /** The console's module scan prunes the same directories. */
    private const EXCLUDED_DIRECTORIES = ['vendor', 'node_modules'];

    /**
     * @param list<string> $directories
     * @param list<string> $projectNamespaces
     * @param array<string, list<string>> $packageSources each discovered package's psr-4
     *                                                    namespace and directories, read
     *                                                    inside that namespace only
     * @param list<string> $excludedDirectories refused packages' directories, never read
     */
    public function __construct(
        private readonly array $directories,
        private readonly array $projectNamespaces,
        private readonly array $packageSources = [],
        private readonly array $excludedDirectories = [],
    ) {
    }

    /**
     * @param list<string> $appModulePaths empty means the whole application root
     * @param list<string> $projectNamespaces
     * @param array<string, list<string>> $packageSources
     * @param list<string> $excludedDirectories
     */
    public static function forPaths(array $appModulePaths, string $rootDir, array $projectNamespaces, array $packageSources = [], array $excludedDirectories = []): self
    {
        $directories = [];
        foreach ($appModulePaths === [] ? [''] : $appModulePaths as $path) {
            $directories[] = self::resolve($path, $rootDir);
        }

        return new self($directories, $projectNamespaces, $packageSources, $excludedDirectories);
    }

    public function members(): Members
    {
        $plugins = [];
        $tags = [];
        $listeners = [];
        $problems = [];

        foreach ($this->classes() as $className) {
            $class = new ReflectionClass($className);
            foreach ($class->getAttributes(Plugin::class) as $attribute) {
                $plugin = $attribute->newInstance();
                $plugins[] = new PluginMember($plugin->contract, $className, $plugin->priority);
            }

            foreach ($class->getAttributes(Tag::class) as $attribute) {
                $tags[] = new TagMember($attribute->newInstance()->name, $className);
            }

            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // An inherited listener is the declaring class's, read when that one is.
                if ($method->class !== $className) {
                    continue;
                }

                foreach ($method->getAttributes(AsListener::class) as $attribute) {
                    $listener = $class->isAbstract()
                        // Its subclasses are not found: a file that only extends it names no attribute.
                        ? sprintf('%s::%s() has #[AsListener] on an abstract class, which cannot be built: move it to the concrete class', $className, $method->getName())
                        : $this->listenerOf($method, $attribute->newInstance()->event);

                    if ($listener instanceof ListenerMember) {
                        $listeners[] = $listener;
                    } else {
                        $problems[] = $listener;
                    }
                }
            }
        }

        usort($plugins, PluginMember::compare(...));
        usort($tags, TagMember::compare(...));
        usort($listeners, ListenerMember::compare(...));

        return new Members($plugins, $tags, $listeners, $problems);
    }

    /**
     * The application's module paths inside `projectNamespaces`, then each
     * package's directories inside its own namespace. A package installed from
     * a path inside the module paths is reached twice and read once.
     *
     * @return list<class-string>
     */
    private function classes(): array
    {
        $sources = [[$this->directories, $this->projectNamespaces]];
        foreach ($this->packageSources as $namespace => $directories) {
            $sources[] = [$directories, [$namespace]];
        }

        $classes = [];
        $listenerTraits = [];
        foreach ($this->filesOf($sources) as [$file, $namespaces]) {
            $declarations = $this->declarationsIn($file);
            if (!$declarations instanceof SourceDeclarations) {
                continue;
            }

            foreach ($this->existing($declarations->classes, $namespaces, class_exists(...)) as $className) {
                $classes[$className] = true;
            }

            foreach ($this->existing($declarations->traits, $namespaces, trait_exists(...)) as $trait) {
                if ($this->declaresAListener($trait)) {
                    $listenerTraits[$trait] = true;
                }
            }
        }

        if ($listenerTraits !== []) {
            foreach ($this->classesUsing(array_keys($listenerTraits), $sources) as $className) {
                $classes[$className] = true;
            }
        }

        return array_keys($classes);
    }

    /**
     * A class using a trait that declares a listener names no attribute of its
     * own, so the first pass never reads it. Only paid for when such a trait
     * exists: every file is read again, and one naming the trait is parsed.
     *
     * @param list<string> $traits
     * @param list<array{list<string>, list<string>}> $sources
     *
     * @return list<class-string>
     */
    private function classesUsing(array $traits, array $sources): array
    {
        $shortNames = array_map(static fn (string $trait): string => substr((string) strrchr('\\' . $trait, '\\'), 1), $traits);

        $classes = [];
        foreach ($this->filesOf($sources) as [$file, $namespaces]) {
            $source = (string) file_get_contents($file->getPathname());
            if (!$this->containsAny($source, $shortNames)) {
                continue;
            }

            foreach ($this->existing(SourceDeclarations::of($source)->classes, $namespaces, class_exists(...)) as $className) {
                if (array_intersect($traits, $this->traitsOf($className)) !== []) {
                    $classes[] = $className;
                }
            }
        }

        return $classes;
    }

    /**
     * @param list<array{list<string>, list<string>}> $sources
     *
     * @return iterable<array{SplFileInfo, list<string>}>
     */
    private function filesOf(array $sources): iterable
    {
        foreach ($sources as [$directories, $namespaces]) {
            foreach ($directories as $directory) {
                if (!is_dir($directory)) {
                    continue;
                }

                foreach ($this->phpFilesIn($directory) as $file) {
                    yield [$file, $namespaces];
                }
            }
        }
    }

    /**
     * @param class-string $trait
     */
    private function declaresAListener(string $trait): bool
    {
        foreach ((new ReflectionClass($trait))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getAttributes(AsListener::class) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every trait the class takes in, through its parents and other traits.
     *
     * @param class-string $className
     *
     * @return list<string>
     */
    private function traitsOf(string $className): array
    {
        $traits = [];
        $pending = [];
        for ($class = new ReflectionClass($className); $class instanceof ReflectionClass; $class = $class->getParentClass()) {
            $pending[] = $class;
        }

        while (($class = array_pop($pending)) instanceof ReflectionClass) {
            foreach ($class->getTraits() as $trait) {
                if (!isset($traits[$trait->getName()])) {
                    $traits[$trait->getName()] = true;
                    $pending[] = $trait;
                }
            }
        }

        return array_keys($traits);
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string|null $event
     *
     * @return ListenerMember|string the member, or what is wrong with it
     */
    private function listenerOf(ReflectionMethod $method, ?string $event): ListenerMember|string
    {
        if ($event === null) {
            $type = ($method->getParameters()[0] ?? null)?->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                return sprintf(
                    '%s::%s() has #[AsListener] and no event: type its first parameter with the event class, or pass it: #[AsListener(Event::class)]',
                    $method->class,
                    $method->getName(),
                );
            }

            /** @var class-string $event */
            $event = $type->getName();
        }

        return new ListenerMember($event, $method->class, $method->getName());
    }

    /**
     * Null for a file that cannot name Gacela's attributes.
     */
    private function declarationsIn(SplFileInfo $file): ?SourceDeclarations
    {
        $source = (string) file_get_contents($file->getPathname());

        // Cheap text checks first, loose on purpose: reflection decides
        // membership from the attribute itself, so a file let through here costs
        // a parse, never a wrong member.
        if (!str_contains($source, '#[') || !str_contains($source, 'Gacela')) {
            return null;
        }

        $declarations = SourceDeclarations::of($source);

        return $declarations->namesAttributeNamespace ? $declarations : null;
    }

    /**
     * @param list<string> $names
     * @param list<string> $namespaces
     * @param Closure(string): bool $exists
     *
     * @return list<class-string>
     */
    private function existing(array $names, array $namespaces, Closure $exists): array
    {
        $found = [];
        foreach ($names as $name) {
            if ($this->isInside($name, $namespaces) && $exists($name)) {
                /** @var class-string $name */
                $found[] = $name;
            }
        }

        return $found;
    }

    /**
     * @param list<string> $namespaces none means any
     */
    private function isInside(string $className, array $namespaces): bool
    {
        if ($namespaces === []) {
            return true;
        }

        foreach ($namespaces as $namespace) {
            if (str_starts_with($className, rtrim($namespace, '\\') . '\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function phpFilesIn(string $directory): iterable
    {
        $excluded = [];
        foreach ($this->excludedDirectories as $excludedDirectory) {
            $excluded[self::comparable($excludedDirectory)] = true;
        }

        $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            static fn (mixed $current, string $key, RecursiveDirectoryIterator $iterator): bool => $iterator->hasChildren()
                ? !str_starts_with($iterator->getFilename(), '.')
                    && !in_array($iterator->getFilename(), self::EXCLUDED_DIRECTORIES, true)
                    && !isset($excluded[self::comparable($iterator->getPathname())])
                : str_ends_with($iterator->getFilename(), '.php'),
        ));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            yield $file;
        }
    }

    /**
     * One separator and no trailing one, so a directory Composer recorded
     * matches the one the iterator walks into on either platform.
     */
    private static function comparable(string $directory): string
    {
        return rtrim(str_replace('\\', '/', $directory), '/');
    }

    /**
     * Normalized like the refused packages' directories, so `./src` or a `..`
     * segment does not walk a refused package under a name it is not refused by.
     */
    private static function resolve(string $path, string $rootDir): string
    {
        if ($path === '') {
            return PackageConfigFinder::normalize($rootDir);
        }

        if (str_starts_with($path, '/') || (strlen($path) > 1 && $path[1] === ':')) {
            return PackageConfigFinder::normalize($path);
        }

        return PackageConfigFinder::normalize(rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
    }
}
