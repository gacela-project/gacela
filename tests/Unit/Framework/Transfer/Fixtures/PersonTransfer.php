<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Transfer\Fixtures;

use Gacela\Framework\Transfer\AbstractTransfer;

/**
 * @method int|null getId()
 * @method string|null getName()
 * @method self setName(string $name)
 * @method int|null getAge()
 * @method self setAge(int $age)
 */
final class PersonTransfer extends AbstractTransfer
{
    public ?int $id = null;

    public ?string $name = null;

    public ?int $age = null;
}
