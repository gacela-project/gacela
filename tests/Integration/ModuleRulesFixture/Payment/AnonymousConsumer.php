<?php

declare(strict_types=1);

namespace GacelaTest\Integration\ModuleRulesFixture\Payment;

use GacelaTest\Integration\ModuleRulesFixture\Admin\AdminBase;

final class AnonymousConsumer
{
    public function create(): object
    {
        return new class() extends AdminBase {};
    }
}
