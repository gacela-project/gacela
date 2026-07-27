<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerTags\Shipping;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\ContainerTags\Validation\ValidatorInterface;

use function array_map;

/**
 * @method Provider getProvider()
 */
final class Factory extends AbstractFactory
{
    /**
     * @return list<string>
     */
    public function createValidatorNames(): array
    {
        /** @var list<ValidatorInterface> $validators */
        $validators = $this->getProvidedDependency(Provider::VALIDATORS);

        return array_map(
            static fn (ValidatorInterface $validator): string => $validator->name(),
            $validators,
        );
    }
}
