<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Factory\Make;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createWidget(): Widget
    {
        return $this->make(Widget::class);
    }

    public function createNamedWidget(string $name): Widget
    {
        return $this->make(Widget::class, ['name' => $name]);
    }
}
