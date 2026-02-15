<?php

declare(strict_types=1);

namespace Tivins\Solid\LSP;

use LogicException;
use ReflectionClass;
use ReflectionMethod;

interface ThrowsDetectorInterface
{
    /**
     * Returns the list of exception types declared in the docblock's "@throws" tags.
     *
     * Supported formats:
     * - "@throws RuntimeException"
     * - "@throws RuntimeException|InvalidArgumentException"
     * - "@throws \RuntimeException" (FQCN)
     * - "@throws RuntimeException Description text"
     *
     * @return string[] Exception class names (normalized without leading \)
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
     * 
     * @throws LogicException if the class is not a class or interface
     */
    public function getUseImportsForClass(ReflectionClass $class): array;

    /**
     * Detects exceptions actually thrown in the method body via AST analysis (nikic/php-parser).
     *
     * Recursively follows internal calls ($this->method()) within the same class,
     * cross-class static calls (ClassName::method()), and calls on locally created
     * instances ((new ClassName())->method()).
     *
     * @return string[] Exception class names (normalized without leading \)
     * 
     * @throws LogicException if the method is not a method
     */
    public function getActualThrows(ReflectionMethod $method): array;

    /**
     * Same as getActualThrows() but returns, for each exception, the call chain(s)
     * that lead to it (e.g. entry point → internal/external calls → method that throws).
     * Used to produce precise violation messages.
     *
     * @return list<array{exception: string, chains: list<string[]>}> Each exception (normalized without leading \)
     *         with one or more chains; each chain is an ordered list of "ClassName::methodName" steps.
     * 
     * @throws LogicException if the method is not a method
     */
    public function getActualThrowsWithChains(ReflectionMethod $method): array;
}
