<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Bootstrap;

use Gacela\Framework\Config\Config;
use RuntimeException;

use function sprintf;
use function str_contains;

/**
 * Refuses to finish booting production against the sandbox payment endpoint.
 *
 * A plugin, not a config schema rule: the schema says the key is a string, and
 * no type can say "not that one". Booting is the last moment this can be caught
 * before money moves.
 */
final class RefuseSandboxGateway
{
    public function __invoke(): void
    {
        $endpoint = Config::getInstance()->getString('payment.gateway_endpoint');

        if (str_contains($endpoint, 'sandbox')) {
            throw new RuntimeException(sprintf(
                'Refusing to boot production against the sandbox gateway "%s".',
                $endpoint,
            ));
        }
    }
}
