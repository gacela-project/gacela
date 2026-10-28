<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\FactoryFacadeFixture;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\PHPStan\FactoryFacadeFixture\Library\PdfFacade;

/**
 * Silent: builds a library class named like a Facade, which is no Facade.
 *
 * @extends AbstractFactory<AbstractConfig>
 */
final class LibraryFactory extends AbstractFactory
{
    public function createPdf(): PdfFacade
    {
        return new PdfFacade();
    }
}
