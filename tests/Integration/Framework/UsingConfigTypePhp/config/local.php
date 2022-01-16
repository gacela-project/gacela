<?php

declare(strict_types=1);

return new class () implements JsonSerializable {
    public function jsonSerialize(): array
    {
        return [
            'local_key' => 3,
            'override_key_from_local' => 4,
        ];
    }
};
