<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

final class PropertyChangeTracker
{
    /** @var array<string,bool> */
    private array $changedProperties = [];

    public function markAsChanged(string $propertyName): void
    {
        $this->changedProperties[$propertyName] = true;
    }

    public function markAsUnchanged(string $propertyName): void
    {
        $this->changedProperties[$propertyName] = false;
    }

    public function isChanged(string $propertyName): bool
    {
        return $this->changedProperties[$propertyName] ?? false;
    }

    /**
     * @return array<string,bool>
     */
    public function getAll(): array
    {
        return $this->changedProperties;
    }
}
