<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

enum ParameterStatus: string
{
    case Bound = 'bound';
    case Autowirable = 'autowirable';
    case HasDefault = 'default';
    case Inject = 'inject';
    case NoTypeHint = 'no-type-hint';
    case ScalarWithoutDefault = 'scalar-without-default';
    case UnboundInterface = 'unbound-interface';
    case MissingType = 'missing-type';
    case UnsupportedType = 'unsupported-type';

    public function isResolvable(): bool
    {
        return match ($this) {
            self::Bound, self::Autowirable, self::HasDefault, self::Inject => true,
            default => false,
        };
    }
}
