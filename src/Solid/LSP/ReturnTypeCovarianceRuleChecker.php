<?php

declare(strict_types=1);

namespace Tivins\Solid\LSP;

use ReflectionClass;
use ReflectionMethod;
use ReflectionType;

/**
 * Checks that the return type of a class method is covariant with the contract method.
 *
 * Covariance means the child return type must be equal to or more specific
 * than the contract return type.
 */
readonly class ReturnTypeCovarianceRuleChecker implements LspRuleCheckerInterface
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
        $classReturnType = $classMethod->getReturnType();
        $contractReturnType = $contractMethod->getReturnType();

        if ($this->isReturnTypeCovariant($classReturnType, $contractReturnType, $class, $contract)) {
            return [];
        }

        return [
            new LspViolation(
                className: $class->getName(),
                methodName: $classMethod->getName(),
                contractName: $contract->getName(),
                reason: sprintf(
                    'return type %s is not covariant with contract return type %s',
                    $this->typeChecker->typeToString($classReturnType),
                    $this->typeChecker->typeToString($contractReturnType),
                ),
            ),
        ];
    }

    private function isReturnTypeCovariant(
        ?ReflectionType $classReturnType,
        ?ReflectionType $contractReturnType,
        ReflectionClass $classContext,
        ReflectionClass $contractContext,
    ): bool {
        // If the contract does not constrain return type, the implementation is always valid.
        if ($contractReturnType === null) {
            return true;
        }

        // Contract constrains return type but implementation does not.
        if ($classReturnType === null) {
            return false;
        }

        return $this->typeChecker->isTypeSubtypeOf($classReturnType, $contractReturnType, $classContext, $contractContext);
    }
}
