<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ValidateConfig\Fixtures;

/**
 * A callable object binding: a factory, which is always a valid value no
 * matter what its key is.
 */
final class InvokableBinding
{
    public function __invoke(): SomeImplementation
    {
        return new SomeImplementation();
    }
}
