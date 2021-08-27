<?php

declare(strict_types=1);

return new class() implements JsonSerializable {
    public function jsonSerialize(): array
    {
        return [
            'config_local' => 2,
            'override' => 5,
        ];
    }
};
