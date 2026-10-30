<?php

declare(strict_types=1);

use Gacela\Framework\AbstractFactory;

final class OtherRootFactory extends AbstractFactory
{
    public function __construct(private readonly string $greeting = 'other')
    {
    }

    public function createHello(): string
    {
        return $this->greeting;
    }
}
