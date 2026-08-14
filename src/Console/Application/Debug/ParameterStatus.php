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

    /**
     * The inspector declined to look, rather than looked and found a problem.
     *
     * Union and intersection types are not walked, so a parameter typed that
     * way is unresolvable only in the sense that nothing here has an opinion
     * about it. That is a gap in this tool, and a check that failed a build
     * over it would be blaming a project for using a language feature.
     */
    public function isNotInspected(): bool
    {
        return $this === self::UnsupportedType;
    }

    /**
     * The container cannot satisfy this parameter, and said so after looking.
     *
     * `MissingType` belongs here rather than beside `UnsupportedType`: a type
     * that does not exist is a typo or a deleted class, not a shape the
     * inspector declined to read.
     */
    public function isFault(): bool
    {
        return !$this->isResolvable() && !$this->isNotInspected();
    }
}
