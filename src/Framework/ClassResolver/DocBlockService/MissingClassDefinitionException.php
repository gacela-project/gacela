<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

use RuntimeException;

final class MissingClassDefinitionException extends RuntimeException
{
    public static function missingDefinition(string $className, string $method, string $found): self
    {
        $suggestions = self::getSuggestions($method);

        return new self("
Missing the concrete return type for the method `{$method}()` (Found: `{$found}`).

Class: `{$className}`

Possible solutions:
{$suggestions}

Learn more: https://gacela-project.com/docs/service-resolution
");
    }

    private static function getSuggestions(string $method): string
    {
        $suggestions = [];

        $suggestions[] = "1. Use the #[ServiceMap] attribute (recommended - fastest):

   use Gacela\Framework\ClassResolver\Attribute\ServiceMap;

   #[ServiceMap('{$method}', YourClass::class)]
   final class YourFacade extends AbstractFacade
   {
       public function {$method}(): YourClass
       {
           return \$this->resolve(YourClass::class);
       }
   }";

        $suggestions[] = "2. Add a DocBlock with the return type:

   /**
    * @method YourClass {$method}()
    */
   final class YourFacade extends AbstractFacade
   {
   }";

        $suggestions[] = "3. Add an inline return type hint:

   final class YourFacade extends AbstractFacade
   {
       public function {$method}(): YourClass
       {
           return \$this->resolve(YourClass::class);
       }
   }";

        return implode("\n\n", $suggestions);
    }
}
