<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use ReflectionClass;
use Throwable;

use function array_slice;
use function basename;
use function dirname;
use function file_get_contents;
use function glob;
use function in_array;
use function is_array;
use function is_string;
use function ltrim;
use function sprintf;
use function strrpos;
use function substr;

/**
 * Looks in the caller's own directory for the class the resolver could not find.
 *
 * "Cannot resolve the `Provider` ... you can fix this by adding the missing
 * `Provider`" is the wrong advice for the two ways this usually fails, and
 * both leave the file sitting in the directory the reader is about to open:
 *
 *  - the file is named as expected but declares something else, typically a
 *    namespace that does not match where the file lives;
 *  - the file extends the right base class under a name nothing looks for --
 *    a typo, or a spelling the finder rules do not build.
 *
 * Neither is found by comparing names for similarity. The first is an exact
 * match against the candidates the finder already tried, and the second is
 * read out of the file, so a name mangled beyond recognition is still found
 * and `WalletFacade.php` is never offered as a near-miss for `WalletProvider`.
 *
 * The directory comes from the caller by reflection rather than from the
 * project's PSR-4 map: the caller is loaded by definition -- it is the object
 * asking for the class -- so its file is known without parsing composer.json.
 */
final class ModuleDirectoryHint
{
    private const MAX_HINTS = 3;

    /** The token kinds a parent class name can arrive as after `extends`. */
    private const NAME_TOKENS = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

    /**
     * @param object|class-string $caller
     * @param list<string> $triedClassNames the candidates the finder built, in the order it tried them
     *
     * @return list<string>
     */
    public static function findNear(object|string $caller, string $resolvableType, array $triedClassNames): array
    {
        $expected = [];
        foreach ($triedClassNames as $className) {
            $expected[self::shortNameOf($className)] = ltrim($className, '\\');
        }

        $hints = [];

        foreach (self::phpFilesBeside($caller) as $file) {
            $hint = self::describe($file, $resolvableType, $expected);
            if ($hint !== null) {
                $hints[] = $hint;
            }
        }

        return array_slice($hints, 0, self::MAX_HINTS);
    }

    /**
     * The caller's own directory, listed.
     *
     * Written as one expression rather than an early return: a caller with no
     * file leaves nothing to build a path out of, and a `return []` that goes
     * missing walks the filesystem root instead -- which is empty of PHP files
     * on a normal machine, so it looks like it worked.
     *
     * @param object|class-string $caller
     *
     * @return list<string>
     */
    private static function phpFilesBeside(object|string $caller): array
    {
        $fileName = self::fileOf($caller);

        return $fileName === null
            ? []
            : (glob(dirname($fileName) . '/*.php') ?: []);
    }

    /**
     * @param array<string,string> $expected short class name => the candidate it came from
     */
    private static function describe(string $file, string $resolvableType, array $expected): ?string
    {
        $shortName = basename($file, '.php');

        if (isset($expected[$shortName])) {
            return sprintf(
                '%s does not declare `%s` -- check the namespace it declares',
                basename($file),
                $expected[$shortName],
            );
        }

        if (self::extendsTheBaseClassOf($file, $resolvableType)) {
            // Names the wanted class rather than pointing at "the list above":
            // the other message this hint appears under has no list, and the
            // name is the thing to act on either way. It is dropped when there
            // is none -- the candidates cannot be rebuilt without a
            // bootstrapped Config, and `looks for ``` says nothing.
            $wanted = self::firstOf($expected);

            return sprintf(
                '%s extends Abstract%s under another name%s',
                basename($file),
                $resolvableType,
                $wanted === '' ? '' : sprintf(' -- the resolver looks for `%s`', $wanted),
            );
        }

        return null;
    }

    /**
     * @param array<string,string> $expected
     */
    private static function firstOf(array $expected): string
    {
        foreach ($expected as $className) {
            return $className;
        }

        return '';
    }

    /**
     * A kind registered through `addResolvableType()` has no `Abstract*` in
     * this framework, and is deliberately still asked the same question: the
     * answer is read out of the file rather than assumed, so a project that
     * declared an `Exporter` kind and gave it its own `AbstractExporter` base
     * gets the same hint the four pillars get. A kind whose base does not
     * exist simply matches nothing.
     */
    private static function extendsTheBaseClassOf(string $file, string $resolvableType): bool
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return false;
        }

        return self::extendsClassNamed($content, 'Abstract' . $resolvableType);
    }

    /**
     * Read out of the token stream rather than matched in the text.
     *
     * "extends AbstractProvider" is a phrase, and it appears in docblocks and
     * in this framework's own remediation tips. Matched as text, the first file
     * reported as the missing Provider was a test that merely quoted the
     * sentence -- so a comment mentioning the base class was enough to be
     * accused of being the class.
     *
     * Both spellings arrive as one token here: `AbstractProvider` as a
     * T_STRING, `\Gacela\Framework\AbstractProvider` as a fully-qualified name.
     */
    private static function extendsClassNamed(string $content, string $baseClass): bool
    {
        $awaitingParent = false;

        foreach (token_get_all($content) as $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_EXTENDS) {
                $awaitingParent = true;
                continue;
            }

            if (!$awaitingParent) {
                continue;
            }

            if (in_array($token[0], self::NAME_TOKENS, true)) {
                if (self::shortNameOf($token[1]) === $baseClass) {
                    return true;
                }

                // Not this one; keep reading, a file may declare more than one class.
                $awaitingParent = false;
            }
        }

        return false;
    }

    /**
     * @param object|class-string $caller
     */
    private static function fileOf(object|string $caller): ?string
    {
        try {
            $fileName = (new ReflectionClass($caller))->getFileName();
        } catch (Throwable) {
            return null;
        }

        return is_string($fileName) ? $fileName : null;
    }

    private static function shortNameOf(string $className): string
    {
        $lastSeparator = strrpos($className, '\\');

        return $lastSeparator === false
            ? $className
            : substr($className, $lastSeparator + 1);
    }
}
