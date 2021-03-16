<?php

declare(strict_types=1);

namespace Gacela\Container\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class ContainerException extends Exception implements ContainerExceptionInterface
{
}
