<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Framework\Config\Schema\ConfigSchema;
use Gacela\Framework\Config\Schema\ConfigSchemaViolation;

use function count;
use function sprintf;

/**
 * The declared configuration schema, read against the environment being
 * checked.
 *
 * A missing key is an error rather than a warning: whatever reads it was going
 * to fail anyway, and the only question is whether that happens here or in a
 * request. Nothing declared means nothing to check, and the check says so
 * rather than reporting a pass it never made.
 */
final class ConfigSchemaCheck implements HealthCheck
{
    /**
     * @param list<ConfigSchemaViolation> $violations
     */
    public function __construct(
        private readonly ConfigSchema $schema,
        private readonly array $violations,
    ) {
    }

    public function name(): string
    {
        return 'config schema';
    }

    public function run(): CheckResult
    {
        if ($this->schema->isEmpty()) {
            return CheckResult::ok($this->name(), 'no schema declared — nothing to check');
        }

        if ($this->violations === []) {
            return CheckResult::ok($this->name(), sprintf(
                '%d declared key(s), all satisfied',
                count($this->schema->declaredKeys()),
            ));
        }

        $details = [];
        foreach ($this->violations as $violation) {
            $details[] = $violation->message;
        }

        return CheckResult::error(
            $this->name(),
            $details,
            'Provide the missing keys for this environment, or change what gacela.php declares.',
        );
    }
}
