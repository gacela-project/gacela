<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceAware\Module\Infrastructure\Command;

use Gacela\Framework\CustomServicesResolverAwareTrait;
use GacelaTest\Feature\Framework\CustomServiceAware\Module\Infrastructure\Db\Repository;

/**
 * @method Repository repository()
 */
final class HelloCommand
{
    use CustomServicesResolverAwareTrait;

    public function echoHello(int $id): void
    {
        $name = $this->repository()->findNameById($id);

        echo "Hello, {$name}";
    }

    protected function servicesMapping(): array
    {
        return [
            'repository' => Repository::class,
        ];
    }
}
