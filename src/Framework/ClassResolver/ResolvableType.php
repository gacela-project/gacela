<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function strlen;

final class ResolvableType
{
    private function __construct(
        private readonly string $resolvableType,
        private readonly string $moduleName,
    ) {
    }

    /**
     * Split the moduleName and resolvableType from a className.
     *
     * The suffixes come from {@see ResolvableTypes}, so a project-declared kind
     * splits like a pillar does -- which is what makes an override of
     * `App\Wallet\WalletReader` land on the key the resolver looks up. Longest
     * suffix first, so a declared `ServiceProvider` wins over the `Provider` it
     * ends with.
     */
    public static function fromClassName(string $className): self
    {
        foreach (ResolvableTypes::matchOrder() as $suffix => $kind) {
            if (str_ends_with($className, $suffix)) {
                $moduleName = substr($className, 0, strlen($className) - strlen($suffix));
                return new self($kind, $moduleName);
            }
        }

        $lastPos = (int)strrpos($className, '\\');
        $customResolvableType = substr($className, $lastPos);
        $moduleName = str_replace($customResolvableType, '', $className);

        return new self(ltrim($customResolvableType, '\\'), $moduleName);
    }

    public function resolvableType(): string
    {
        return $this->resolvableType;
    }

    public function moduleName(): string
    {
        return $this->moduleName;
    }
}
