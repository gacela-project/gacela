<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

use Gacela\Framework\Attribute\AsListener;
use Gacela\Framework\Attribute\Plugin;
use Gacela\Framework\Attribute\Tag;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileInfo;

use function class_exists;
use function file_get_contents;
use function in_array;
use function is_dir;
use function ltrim;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function usort;

use const DIRECTORY_SEPARATOR;

/**
 * Finds the `#[Plugin]`, `#[Tag]` and `#[AsListener]` declarations of the
 * application by walking its module paths.
 *
 * A file is loaded only when its source names `Gacela\Framework\Attribute` and declares a class
 * inside `projectNamespaces`: a loose match costs one class load, never a wrong
 * member, because membership is read from the attribute itself.
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
     */
    public function __construct(
        private readonly array $directories,
        private readonly array $projectNamespaces,
    ) {
    }

    /**
     * @param list<string> $appModulePaths empty means the whole application root
     * @param list<string> $projectNamespaces
     */
    public static function forPaths(array $appModulePaths, string $rootDir, array $projectNamespaces): self
    {
        $directories = [];
        foreach ($appModulePaths === [] ? [''] : $appModulePaths as $path) {
            $directories[] = self::resolve($path, $rootDir);
        }

        return new self($directories, $projectNamespaces);
    }

    public function members(): Members
    {
        $plugins = [];
        $tags = [];
        $listeners = [];
        $problems = [];

        foreach ($this->directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach ($this->phpFilesIn($directory) as $file) {
                $className = $this->candidateClassIn($file);
                if ($className === null) {
                    continue;
                }

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
        }

        usort($plugins, PluginMember::compare(...));
        usort($tags, TagMember::compare(...));
        usort($listeners, ListenerMember::compare(...));

        return new Members($plugins, $tags, $listeners, $problems);
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
     * @return class-string|null
     */
    private function candidateClassIn(SplFileInfo $file): ?string
    {
        $source = (string) file_get_contents($file->getPathname());

        // Loose on purpose: an aliased, grouped or qualified use of either
        // attribute still names their namespace, and reflection decides
        // membership from the attribute itself.
        if (!str_contains($source, '#[') || !str_contains($source, 'Gacela\\Framework\\Attribute')) {
            return null;
        }

        // The class keyword may follow its attributes on the same line.
        if (preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $source, $namespace) !== 1
            || preg_match('/^\s*(?:#\[.*?\]\s*)*(?:(?:final|abstract|readonly)\s+)*class\s+(\w+)/m', $source, $class) !== 1
        ) {
            return null;
        }

        $className = $namespace[1] . '\\' . $class[1];

        if (!$this->isInsideProjectNamespaces($className) || !class_exists($className)) {
            return null;
        }

        return $className;
    }

    private function isInsideProjectNamespaces(string $className): bool
    {
        if ($this->projectNamespaces === []) {
            return true;
        }

        foreach ($this->projectNamespaces as $namespace) {
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
        $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            static fn (mixed $current, string $key, RecursiveDirectoryIterator $iterator): bool => $iterator->hasChildren()
                ? !str_starts_with($iterator->getFilename(), '.')
                    && !in_array($iterator->getFilename(), self::EXCLUDED_DIRECTORIES, true)
                : str_ends_with($iterator->getFilename(), '.php'),
        ));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            yield $file;
        }
    }

    private static function resolve(string $path, string $rootDir): string
    {
        if ($path === '') {
            return $rootDir;
        }

        if (str_starts_with($path, '/') || (strlen($path) > 1 && $path[1] === ':')) {
            return $path;
        }

        return rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}
