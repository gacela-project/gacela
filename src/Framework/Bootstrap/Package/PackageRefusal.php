<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Package;

/**
 * Why a declared package config was not merged.
 */
enum PackageRefusal: string
{
    /**
     * The project named it in `dontDiscover()`, so the file was never read.
     */
    case OptedOut = 'opted out';

    /**
     * The declaration names a file that is not there.
     */
    case MissingFile = 'file not found';

    /**
     * The file is there and does not return a `callable(GacelaConfig)`.
     */
    case NotCallable = 'does not return a callable';

    /**
     * Whether this is a broken declaration rather than a decision.
     *
     * An opt-out is the project getting what it asked for; the other two are a
     * package that cannot contribute and does not know it.
     */
    public function isBroken(): bool
    {
        return $this !== self::OptedOut;
    }
}
