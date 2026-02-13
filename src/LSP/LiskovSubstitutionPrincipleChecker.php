<?php

declare(strict_types=1);

namespace Tivins\LSP;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * Checks if a class violates the Liskov Substitution Principle
 * with respect to its interfaces and parent class.
 *
 * Currently checks:
 * - Exception contract violations via docblock (@throws not declared in parent/interface)
 * - Exception contract violations via AST (actual throw statements not allowed by contract)
 * - Exception hierarchy: throwing a subclass of a contract-allowed exception is allowed (LSP-compliant).
 *
 * @todo Check parameter type contravariance (preconditions)
 * @todo Check return type covariance (postconditions)
 */
readonly class LiskovSubstitutionPrincipleChecker
{
    public function __construct(private ThrowsDetector $throwsDetector)
    {
    }

    /**
     * Check a class for LSP violations against all its contracts (interfaces + parent class).
     *
     * @return LspViolation[] List of violations found (empty if none)
     * @throws ReflectionException
     */
    public function check(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $violations = [];

        // Check against all implemented interfaces
        foreach ($reflection->getInterfaces() as $interface) {
            $violations = array_merge(
                $violations,
                $this->checkAgainstContract($reflection, $interface)
            );
        }

        // Check against parent class (if any)
        $parentClass = $reflection->getParentClass();
        if ($parentClass !== false) {
            $violations = array_merge(
                $violations,
                $this->checkAgainstContract($reflection, $parentClass)
            );
        }

        return $violations;
    }

    /**
     * Compare all methods of a class against a contract (interface or parent class).
     *
     * @return LspViolation[]
     */
    private function checkAgainstContract(ReflectionClass $class, ReflectionClass $contract): array
    {
        $violations = [];

        foreach ($contract->getMethods() as $contractMethod) {
            // Only check methods that the class defines itself (not inherited as-is)
            if (!$class->hasMethod($contractMethod->getName())) {
                continue;
            }

            $classMethod = $class->getMethod($contractMethod->getName());

            // Skip if the class method is the same as the contract method (inherited, not overridden)
            if ($classMethod->getDeclaringClass()->getName() === $contract->getName()) {
                continue;
            }

            $violations = array_merge(
                $violations,
                $this->checkThrowsViolations($class, $classMethod, $contract, $contractMethod)
            );
        }

        return $violations;
    }

    /**
     * Check if a class method declares or actually throws exceptions not allowed by the contract.
     *
     * Two types of violations are detected:
     * - Docblock violations: @throws declarations not present in the contract
     * - Code violations: actual throw statements (AST) for exceptions not in the contract
     *
     * @return LspViolation[]
     */
    private function checkThrowsViolations(
        ReflectionClass  $class,
        ReflectionMethod $classMethod,
        ReflectionClass  $contract,
        ReflectionMethod $contractMethod,
    ): array {
        $violations = [];

        $contractThrows = $this->throwsDetector->getDeclaredThrows($contractMethod);
        $classThrowsDeclared = $this->throwsDetector->getDeclaredThrows($classMethod);
        $classThrowsActual = $this->throwsDetector->getActualThrows($classMethod);

        // Violation if the class DECLARES throws not allowed by the contract (strict or subclass)
        foreach ($classThrowsDeclared as $exceptionType) {
            if ($this->isExceptionAllowedByContract($exceptionType, $contractThrows, $class, $contract)) {
                continue;
            }
            $violations[] = new LspViolation(
                className: $class->getName(),
                methodName: $classMethod->getName(),
                contractName: $contract->getName(),
                reason: sprintf(
                    '@throws %s declared in docblock but not allowed by the contract',
                    $exceptionType,
                ),
            );
        }

        // Violation if the class ACTUALLY throws exceptions not allowed by the contract (strict or subclass)
        foreach ($classThrowsActual as $exceptionType) {
            if ($this->isExceptionAllowedByContract($exceptionType, $contractThrows, $class, $contract)) {
                continue;
            }
            $violations[] = new LspViolation(
                className: $class->getName(),
                methodName: $classMethod->getName(),
                contractName: $contract->getName(),
                reason: sprintf(
                    'throws %s in code (detected via AST) but not allowed by the contract',
                    $exceptionType,
                ),
            );
        }

        return $violations;
    }

    /**
     * Resolve an exception type name to its FQCN using the class namespace.
     * Ensures a leading backslash for global namespace so class_exists/is_subclass_of resolve correctly.
     *
     * For unqualified names (e.g. "RuntimeException") in a namespaced class, we check whether
     * a class with that name actually exists in the namespace. If not, we fall back to the global
     * namespace. This correctly handles standard PHP exceptions (Exception, RuntimeException, etc.)
     * used with short names inside namespaced code.
     */
    private function resolveExceptionFqcn(string $type, ReflectionClass $class): string
    {
        $type = ltrim($type, '\\');
        // Multi-segment name (e.g. "Foo\BarException") → already a namespace path, treat as FQCN
        if (str_contains($type, '\\')) {
            return '\\' . $type;
        }
        // Unqualified name in a namespaced class: resolve with class_exists check
        $namespace = $class->getNamespaceName();
        if ($namespace !== '') {
            $namespacedType = '\\' . $namespace . '\\' . $type;
            if (class_exists($namespacedType)) {
                return $namespacedType;
            }
            // Not found in namespace → fall back to global (standard PHP exceptions, etc.)
            return '\\' . $type;
        }
        return '\\' . $type;
    }

    /**
     * Return true if the thrown exception type is allowed by the contract:
     * same type or a subclass of any exception declared in the contract (LSP-compliant).
     */
    private function isExceptionAllowedByContract(
        string $thrownType,
        array $contractThrows,
        ReflectionClass $class,
        ReflectionClass $contract,
    ): bool {
        if (empty($contractThrows)) {
            return false;
        }
        $thrownFqcn = $this->resolveExceptionFqcn($thrownType, $class);
        foreach ($contractThrows as $contractType) {
            $contractFqcn = $this->resolveExceptionFqcn($contractType, $contract);
            if ($thrownFqcn === $contractFqcn) {
                return true;
            }
            if (class_exists($thrownFqcn) && class_exists($contractFqcn)
                && is_subclass_of($thrownFqcn, $contractFqcn)) {
                return true;
            }
        }
        return false;
    }
}