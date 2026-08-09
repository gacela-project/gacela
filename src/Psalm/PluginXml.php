<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Psalm\Exception\ConfigException;
use SimpleXMLElement;

use function count;
use function sprintf;
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
     * count is what says whether one was written. `instanceof` alone is true
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
        $segments = trim((string)($element['modulePathSegments'] ?? ''));

        if ($segments === '') {
            return 1;
        }

        if ((int)$segments < 1) {
            throw new ConfigException(
                'modulePathSegments must be a positive number of namespace segments, got: ' . $segments,
            );
        }

        return (int)$segments;
    }

    /**
     * @return list<string>
     */
    public static function sharedNamespaces(SimpleXMLElement $element): array
    {
        $namespaces = [];

        foreach ($element->sharedNamespace as $sharedNamespace) {
            $name = trim((string)$sharedNamespace);
            if ($name !== '') {
                $namespaces[] = $name;
            }
        }

        return $namespaces;
    }
}
