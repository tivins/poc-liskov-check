<?php

declare(strict_types=1);

namespace Tivins\LSP;

use ReflectionMethod;
use Exception;

class ThrowsDetector
{
    /**
     * Retourne la liste des exceptions déclarées dans le @throws du docblock.
     *
     * Formats supportés :
     * - `@throws RuntimeException`
     * - `@throws RuntimeException|InvalidArgumentException`
     * - `@throws \RuntimeException` (FQCN)
     * - `@throws RuntimeException Description text`
     *
     * @return string[] Noms des classes d'exception (normalisés sans le \ initial)
     */
    public function getDeclaredThrows(ReflectionMethod $method): array
    {
        $docblock = $method->getDocComment();
        if ($docblock === false) {
            return [];
        }

        $throws = [];

        // Match only proper @throws tags (at the start of a docblock line, after * )
        if (preg_match_all('/^\s*\*\s*@throws\s+([^\s*]+)/m', $docblock, $matches)) {
            foreach ($matches[1] as $throwsDeclaration) {
                // Handle piped exception types: RuntimeException|InvalidArgumentException
                $types = explode('|', $throwsDeclaration);
                foreach ($types as $type) {
                    $type = ltrim(trim($type), '\\');
                    if ($type !== '') {
                        $throws[] = $type;
                    }
                }
            }
        }

        return array_unique($throws);
    }

    /**
     * Détecte les exceptions réellement lancées dans le corps de la méthode.
     * (nécessite php-parser pour l'AST)
     *
     * @return string[] FQCN des classes d'exception
     * @todo Implémenter via nikic/php-parser pour une analyse AST fiable
     */
    public function getActualThrows(ReflectionMethod $method): array
    {
        throw new Exception("Not implemented yet. Requires nikic/php-parser for AST analysis.");
    }
}