<?php

declare(strict_types=1);

namespace Tivins\Solid\LSP;

use ReflectionClass;
use ReflectionException;

/**
 * Checks if a class violates the Liskov Substitution Principle
 * with respect to its interfaces and parent class.
 *
 * This class acts as an orchestrator: it resolves contracts (interfaces + parent),
 * iterates over methods, and delegates each check to the registered rule checkers
 * (Strategy pattern).
 *
 * Currently supported rules (via LspRuleCheckerInterface implementations):
 * - Exception contract violations (ThrowsContractRuleChecker)
 * - Return type covariance (ReturnTypeCovarianceRuleChecker)
 * - Parameter type contravariance (ParameterTypeContravarianceRuleChecker)
 */
readonly class LiskovSubstitutionPrincipleChecker
{
    /**
     * @param LspRuleCheckerInterface[] $ruleCheckers
     */
    public function __construct(private array $ruleCheckers)
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
     * Compare all methods of a class against a contract (interface or parent class)
     * by delegating to each registered rule checker.
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

            foreach ($this->ruleCheckers as $ruleChecker) {
                $violations = array_merge(
                    $violations,
                    $ruleChecker->check($class, $classMethod, $contract, $contractMethod)
                );
            }
        }

        return $violations;
    }
}
