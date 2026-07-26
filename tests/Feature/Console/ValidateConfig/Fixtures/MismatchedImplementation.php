<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ValidateConfig\Fixtures;

/**
 * Incompatible with {@see SomeContract} but with both a parent class and an
 * interface, so the mismatch report has a full type chain to describe.
 */
final class MismatchedImplementation extends BaseImplementation implements OtherContract
{
}
