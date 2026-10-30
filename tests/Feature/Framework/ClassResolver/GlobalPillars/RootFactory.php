<?php

declare(strict_types=1);

use Gacela\Framework\AbstractFactory;

final class RootFactory extends AbstractFactory
{
    public function __construct(private readonly string $greeting = 'root')
    {
    }

    public function createHello(): string
    {
        return $this->greeting;
    }
}
