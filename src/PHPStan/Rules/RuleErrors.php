<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\Violation;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Turns the host-agnostic findings into PHPStan's own error objects.
 *
 * @internal
 */
final class RuleErrors
{
    /**
     * @param list<Violation> $violations
     *
     * @return list<IdentifierRuleError>
     */
    public static function from(array $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $builder = RuleErrorBuilder::message($violation->message)
                ->identifier($violation->identifier);

            // Left unset, PHPStan reports the line of the analysed node, which
            // is what every rule but the interface-drift one wants.
            if ($violation->node !== null) {
                $builder->line($violation->node->getStartLine());
            }

            $errors[] = $builder->build();
        }

        return $errors;
    }
}
