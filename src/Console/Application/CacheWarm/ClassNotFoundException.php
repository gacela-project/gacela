<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use Gacela\Framework\Exception\ErrorSuggestionHelper;
use RuntimeException;

use function sprintf;

final class ClassNotFoundException extends RuntimeException
{
    /**
     * @param list<string> $availableClasses
     */
    public function __construct(string $className, array $availableClasses = [])
    {
        $message = sprintf('Class not found: %s', $className);
        $message .= ErrorSuggestionHelper::suggestSimilar($className, $availableClasses);
        $message .= ErrorSuggestionHelper::addHelpfulTip('class_not_found');

        parent::__construct($message);
    }
}
