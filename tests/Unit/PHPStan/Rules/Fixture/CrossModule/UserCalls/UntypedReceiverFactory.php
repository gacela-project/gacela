<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\UserCalls;

/**
 * An unresolvable receiver is not evidence of a violation. Reporting it would
 * turn the rule into noise on every codebase that has any mixed left.
 */
final class UntypedReceiverFactory
{
    /**
     * @param mixed $anything
     */
    public function build($anything): mixed
    {
        return $anything->run();
    }
}
