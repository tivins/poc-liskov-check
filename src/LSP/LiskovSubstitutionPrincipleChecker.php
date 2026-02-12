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
 * - Exception contract violations (throws not declared in parent/interface)
 *
 * @todo Check parameter type contravariance (preconditions)
 * @todo Check return type covariance (postconditions)
 */
class LiskovSubstitutionPrincipleChecker
{
    private ThrowsDetector $throwsDetector;

    public function __construct(?ThrowsDetector $throwsDetector = null)
    {
        $this->throwsDetector = $throwsDetector ?? new ThrowsDetector();
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
     * Check if a class method declares exceptions not allowed by the contract method.
     *
     * A violation occurs when the class method declares @throws for exception types
     * that are not declared in the contract (interface/parent) method.
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
        $classThrows = $this->throwsDetector->getDeclaredThrows($classMethod);

        // Find exceptions declared in the class but not in the contract
        $unexpectedThrows = array_diff($classThrows, $contractThrows);

        foreach ($unexpectedThrows as $exceptionType) {
            $violations[] = new LspViolation(
                className: $class->getName(),
                methodName: $classMethod->getName(),
                contractName: $contract->getName(),
                reason: sprintf(
                    'Throws %s which is not declared in the contract',
                    $exceptionType,
                ),
            );
        }

        return $violations;
    }
}