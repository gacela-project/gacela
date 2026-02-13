<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use RuntimeException;

use function sprintf;

final class ServiceNotFoundException extends RuntimeException
{
    /**
     * @param list<string> $availableServices
     */
    public function __construct(string $className, array $availableServices = [])
    {
        $message = sprintf('Service "%s" not found in the container.', $className);
        $message .= ErrorSuggestionHelper::suggestSimilar($className, $availableServices);
        $message .= ErrorSuggestionHelper::addHelpfulTip('service_not_found');

        parent::__construct($message);
    }
}
