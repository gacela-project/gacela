<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\MissingPillarMethodException;
use PHPUnit\Framework\TestCase;

final class MissingPillarMethodExceptionTest extends TestCase
{
    /**
     * The caller is what the hint reads the directory off, and the resolvers
     * only have one once `resolve()` has been called. Without it there is no
     * directory to look in, and the message is the one it has always been.
     */
    public function test_without_a_caller_the_message_carries_no_hint(): void
    {
        $exception = MissingPillarMethodException::onDefault('Factory', 'Wallet', 'createThing', 'WalletFactory');

        self::assertSame(
            "Module `Wallet` has no `Factory`, so `createThing()` has nowhere to be defined.\n"
            . 'Add `WalletFactory` (or check its filename matches its class name'
            . ' -- `gacela doctor` reports that too).',
            $exception->getMessage(),
        );
    }
}
