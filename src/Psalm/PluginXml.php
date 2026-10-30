<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Gacela\StaticAnalysis\PublicApiSurface;
use Psalm\Exception\ConfigException;
use SimpleXMLElement;

use function count;
use function ctype_digit;
use function filter_var;
use function in_array;
use function ltrim;
use function sprintf;
use function substr;
use function trim;

/**
 * Reading the plugin's own configuration element, once.
 *
 * Both opt-in checks are configured the same way -- a root namespace, how many
 * segments name a module, which namespaces are shared -- and both have to fail
 * loudly on a half-written element rather than quietly not running.
 *
 * @internal
 */
final class PluginXml
{
    /**
     * Null when the element was not written, which is a check staying off.
     *
     * Reading an absent child yields an *empty* element rather than null, so the
     * count is what says whether one was written. It counts the matching sibling
     * elements, not the children, so `<crossModule rootNamespace="App"/>` counts
     * one and is configured. `instanceof` alone is true
     * either way, and would turn "not configured" into "configured with
     * nothing", which throws.
     */
    public static function element(?SimpleXMLElement $child): ?SimpleXMLElement
    {
        if (!$child instanceof SimpleXMLElement) {
            return null;
        }

        return count($child) === 0 ? null : $child;
    }

    /**
     * @throws ConfigException
     */
    public static function requiredAttribute(SimpleXMLElement $element, string $attribute, string $why): string
    {
        $value = trim((string)($element[$attribute] ?? ''));

        if ($value === '') {
            throw new ConfigException(sprintf('<%s> needs a %s: %s', $element->getName(), $attribute, $why));
        }

        return $value;
    }

    /**
     * @throws ConfigException
     */
    public static function modulePathSegments(SimpleXMLElement $element): int
    {
        if (!isset($element['modulePathSegments'])) {
            return 1;
        }

        $segments = trim((string)$element['modulePathSegments']);
        $depth = filter_var(ltrim($segments, '0'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!ctype_digit($segments) || $depth === false) {
            throw new ConfigException(
                'modulePathSegments must be a positive number of namespace segments, got: ' . $segments,
            );
        }

        return $depth;
    }

    /**
     * @param list<string> $attributes
     * @param list<string> $children
     */
    public static function rejectUnknown(SimpleXMLElement $element, array $attributes, array $children): void
    {
        self::rejectUnknownAttributes($element, $attributes);
        self::rejectUnknownChildren($element, $children);
    }

    /**
     * @return list<string>
     */
    public static function sharedNamespaces(SimpleXMLElement $element): array
    {
        return self::childValues($element, 'sharedNamespace');
    }

    /**
     * Receivers a cross-module call may land on whatever module they belong to.
     *
     * @return list<string>
     */
    public static function ignoreReceivers(SimpleXMLElement $element): array
    {
        return self::childValues($element, 'ignoreReceiver');
    }

    /**
     * The sub-namespace names a module publishes, defaulted when the project
     * writes none -- the same default `CrossModuleMethodCallRule` carries on the
     * PHPStan side, from the same constant, so the two hosts cannot drift.
     *
     * Whether an element was *written* decides, not what it says -- which is what
     * makes "not configured" and "configured with nothing" different answers: a
     * lone `<publicApiSegment/>` turns the convention off and leaves
     * `#[PublicApi]` as the only way to export a class. Reading the values first
     * would collapse the two, since {@see childValues()} drops the empty one, and
     * opting out would have no spelling at all.
     *
     * {@see element()} is what tells them apart, for the same reason it does
     * there: an absent child reads as an empty element rather than as null.
     *
     * @return list<string>
     */
    public static function publicApiSegments(SimpleXMLElement $element): array
    {
        if (!self::element($element->publicApiSegment) instanceof SimpleXMLElement) {
            return PublicApiSurface::DEFAULT_SEGMENTS;
        }

        return self::childValues($element, 'publicApiSegment');
    }

    /**
     * @param list<string> $allowed
     */
    private static function rejectUnknownAttributes(SimpleXMLElement $element, array $allowed): void
    {
        foreach ($element->attributes() ?? [] as $name => $attribute) {
            if (!in_array($name, $allowed, true)) {
                throw new ConfigException(sprintf('<%s> does not take attribute "%s".', $element->getName(), $name));
            }
        }
    }

    /**
     * @param list<string> $allowed
     */
    private static function rejectUnknownChildren(SimpleXMLElement $element, array $allowed): void
    {
        /** @var iterable<SimpleXMLElement> $children */
        $children = $element->children() ?? [];
        foreach ($children as $child) {
            $name = $child->getName();
            if (in_array($name, $allowed, true)) {
                continue;
            }

            $singular = substr($name, 0, -1);
            $hint = in_array($singular, $allowed, true) && $name === $singular . 's'
                ? sprintf('; did you mean <%s>?', $singular)
                : '.';
            throw new ConfigException(sprintf('<%s> does not take <%s>%s', $element->getName(), $name, $hint));
        }
    }

    /**
     * Every non-empty `<$child>` under the element, trimmed -- whitespace around
     * an xml value is formatting rather than part of the name.
     *
     * @return list<string>
     */
    private static function childValues(SimpleXMLElement $element, string $child): array
    {
        $values = [];

        /** @var iterable<SimpleXMLElement> $nodes reading a child by name yields the elements with it */
        $nodes = $element->{$child};

        foreach ($nodes as $node) {
            $value = trim((string)$node);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }
}
