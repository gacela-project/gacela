<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\FlexibleService;

use Gacela\Framework\AbstractFlexibleService;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class FlexibleServiceResolver extends AbstractClassResolver
{
    /**
     * @throws FlexibleServiceNotFoundException
     */
    public function resolve(object $callerClass): AbstractFlexibleService
    {
        /** @var ?AbstractFlexibleService $resolved */
        $resolved = $this->doResolve($callerClass);

        if (null === $resolved) {
            throw new FlexibleServiceNotFoundException($callerClass);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'FlexibleService';
    }
}
