<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use Gacela\Framework\Bootstrap\GacelaConfig;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

use function array_keys;
use function file_get_contents;
use function preg_match_all;
use function sprintf;

/**
 * Holds RFC-0003's audit table against the code.
 *
 * The table is not decoration: it is the rule that admitted every public
 * `GacelaConfig` method, one row each. A method without a row was added
 * without saying which rule admits it, and a row without a method is doctrine
 * about something that no longer exists.
 */
final class BootstrapSurfaceDocsTest extends TestCase
{
    private const RFC = __DIR__ . '/../../../docs/rfc/0003-bootstrap-configuration-surface.md';

    public function test_every_public_method_has_a_row_in_the_audit_table(): void
    {
        $audited = $this->auditedMethods();

        foreach ($this->publicMethods() as $method) {
            self::assertArrayHasKey(
                $method,
                $audited,
                sprintf(
                    "%s() is public on GacelaConfig but has no row in RFC-0003's audit table. "
                    . 'Add the row -- it is where you write down which rule admits the method.',
                    $method,
                ),
            );
        }
    }

    public function test_the_audit_table_names_no_method_that_stopped_existing(): void
    {
        $public = [];
        foreach ($this->publicMethods() as $method) {
            $public[$method] = true;
        }

        foreach (array_keys($this->auditedMethods()) as $audited) {
            self::assertArrayHasKey(
                $audited,
                $public,
                sprintf("RFC-0003's audit table lists %s(), which is not a public GacelaConfig method anymore.", $audited),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function publicMethods(): array
    {
        $methods = [];

        foreach ((new ReflectionClass(GacelaConfig::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor()) {
                continue;
            }

            if ($method->isStatic()) {
                continue;
            }

            $methods[] = $method->getName();
        }

        return $methods;
    }

    /**
     * The audit rows: every backticked `method()` opening a table row.
     *
     * @return array<string, true>
     */
    private function auditedMethods(): array
    {
        $markdown = (string)file_get_contents(self::RFC);

        preg_match_all('/^\| `(\w+)\(\)`/m', $markdown, $matches);

        $methods = [];
        foreach ($matches[1] as $method) {
            $methods[$method] = true;
        }

        return $methods;
    }
}
