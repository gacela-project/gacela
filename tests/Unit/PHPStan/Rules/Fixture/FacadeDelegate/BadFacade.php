<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeDelegate;

use Gacela\Framework\AbstractFacade;
use stdClass;

final class BadFacade extends AbstractFacade
{
    public function multipleStatements(): string
    {
        $value = $this->getFactory()->createService()->run();

        return $value;
    }

    public function localLogic(int $x): int
    {
        return $x + 1;
    }

    public function controlFlow(bool $flag): string
    {
        if ($flag) {
            return $this->getFactory()->createA()->run();
        }

        return $this->getFactory()->createB()->run();
    }

    public function notAllowedRoot(): string
    {
        return $this->somethingElse()->run();
    }

    public function cachedNonDelegation(int $x): int
    {
        return $this->cached(static fn (): int => $x + 1);
    }

    public function cachedMultiStmt(): string
    {
        return $this->cached(function (): string {
            $value = $this->getFactory()->createService()->run();

            return $value;
        });
    }

    public function somethingElse(): object
    {
        return new stdClass();
    }
}
