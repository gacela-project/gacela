<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

/**
 * The class under analysis, as much of it as the rules need, expressed in the
 * only terms both PHPStan and Psalm can answer.
 *
 * This is the entire seam between a rule and its host analyser. Everything else
 * a rule looks at comes from the php-parser AST, which both hosts build from the
 * same library -- so the rules themselves are host-agnostic and exist once.
 *
 * Every method is about the analysed class itself; there is no lookup by name,
 * because an implementation only ever holds the one class the host handed it.
 */
interface AnalysedClassInterface
{
    /**
     * The fully qualified name. Anonymous classes are excluded before a rule is
     * reached, so this is always a real name.
     */
    public function name(): string;

    public function extendsClass(string $parent): bool;

    /**
     * @return list<string> fully qualified names of the interfaces implemented,
     *                      inherited ones included
     */
    public function interfaceNames(): array;

    /**
     * @param string $interface one of {@see interfaceNames()}; anything else
     *                          answers false rather than throwing, because a
     *                          rule asking about an interface the class does not
     *                          implement has already made its decision
     */
    public function interfaceHasMethod(string $interface, string $method): bool;
}
