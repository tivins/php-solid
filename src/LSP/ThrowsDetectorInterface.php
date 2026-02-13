<?php

declare(strict_types=1);

namespace Tivins\LSP;

use ReflectionClass;
use ReflectionMethod;

interface ThrowsDetectorInterface
{
    /**
     * Retourne la liste des exceptions déclarées dans le "@throws" du docblock.
     *
     * Formats supportés :
     * - "@throws RuntimeException"
     * - "@throws RuntimeException|InvalidArgumentException"
     * - "@throws \RuntimeException" (FQCN)
     * - "@throws RuntimeException Description text"
     *
     * @return string[] Noms des classes d'exception (normalisés sans le \ initial)
     */
    public function getDeclaredThrows(ReflectionMethod $method): array;

    /**
     * Extract the use import map for the file and namespace containing the given class.
     *
     * Returns a map of short alias → FQCN (without leading \).
     * For example, `use Foo\Bar\BazException;` produces ['BazException' => 'Foo\Bar\BazException'].
     * Aliased imports like `use Foo\Bar as Baz;` produce ['Baz' => 'Foo\Bar'].
     *
     * @return array<string, string> short name → FQCN (without leading \)
     */
    public function getUseImportsForClass(ReflectionClass $class): array;

    /**
     * Détecte les exceptions réellement lancées dans le corps de la méthode
     * via analyse AST (nikic/php-parser).
     *
     * Suit récursivement les appels internes ($this->method()) au sein de la même classe,
     * les appels statiques cross-classe (ClassName::method()) et les appels sur instances
     * créées localement ((new ClassName())->method()).
     *
     * @return string[] Noms des classes d'exception (normalisés sans le \ initial)
     */
    public function getActualThrows(ReflectionMethod $method): array;
}
