<?php

declare(strict_types=1);

namespace Tivins\Solid\LSP;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;

/**
 * Checks that parameter types of a class method are contravariant with the contract method.
 *
 * Contravariance means each parameter in the implementation must accept at least everything
 * the contract parameter accepts (same or wider type). Strengthening preconditions is a violation.
 */
readonly class ParameterTypeContravarianceRuleChecker implements LspRuleCheckerInterface
{
    public function __construct(private TypeSubtypeChecker $typeChecker)
    {
    }

    /** @inheritDoc */
    public function check(
        ReflectionClass  $class,
        ReflectionMethod $classMethod,
        ReflectionClass  $contract,
        ReflectionMethod $contractMethod,
    ): array {
        $violations = [];

        $contractParams = $contractMethod->getParameters();
        $classParams = $classMethod->getParameters();

        foreach ($contractParams as $i => $contractParam) {
            // If the class method has fewer parameters at this position, skip
            // (PHP enforces signature compatibility).
            if (!isset($classParams[$i])) {
                continue;
            }

            $classParam = $classParams[$i];
            $contractParamType = $contractParam->getType();
            $classParamType = $classParam->getType();

            if ($this->isParameterTypeContravariant($classParamType, $contractParamType, $class, $contract)) {
                continue;
            }

            $violations[] = new LspViolation(
                className: $class->getName(),
                methodName: $classMethod->getName(),
                contractName: $contract->getName(),
                reason: sprintf(
                    'parameter $%s type %s is not contravariant with contract parameter type %s',
                    $classParam->getName(),
                    $this->typeChecker->typeToString($classParamType),
                    $this->typeChecker->typeToString($contractParamType),
                ),
            );
        }

        return $violations;
    }

    /**
     * Check if a class parameter type is contravariant with a contract parameter type.
     *
     * Contravariance: the class parameter must accept at least everything the contract
     * parameter accepts. This means the contract type must be a subtype of (or equal to)
     * the class type.
     */
    public function isParameterTypeContravariant(
        ?ReflectionType $classParamType,
        ?ReflectionType $contractParamType,
        ReflectionClass $classContext,
        ReflectionClass $contractContext,
    ): bool {
        // If the contract does not constrain the parameter type (implicitly mixed),
        // the implementation must also accept anything.
        if ($contractParamType === null) {
            if ($classParamType === null) {
                return true;
            }
            // Explicit `mixed` is equivalent to no type constraint.
            if ($classParamType instanceof ReflectionNamedType && strtolower($classParamType->getName()) === 'mixed') {
                return true;
            }
            // Adding a type constraint where the contract has none → strengthening preconditions.
            return false;
        }

        // Contract constrains parameter type but implementation does not (implicitly mixed):
        // the implementation accepts anything → valid widening.
        if ($classParamType === null) {
            return true;
        }

        // Both have types: contract type must be a subtype of class type (contravariance).
        // We reuse isTypeSubtypeOf with swapped roles: the contract's type acts as the "child"
        // (must fit into the class's type which acts as the "parent").
        return $this->typeChecker->isTypeSubtypeOf($contractParamType, $classParamType, $contractContext, $classContext);
    }
}
