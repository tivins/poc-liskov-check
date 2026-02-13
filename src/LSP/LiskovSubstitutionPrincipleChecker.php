<?php

declare(strict_types=1);

namespace Tivins\LSP;

use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Checks if a class violates the Liskov Substitution Principle
 * with respect to its interfaces and parent class.
 *
 * Currently checks:
 * - Exception contract violations via docblock (@throws not declared in parent/interface)
 * - Exception contract violations via AST (actual throw statements not allowed by contract)
 * - Exception hierarchy: throwing a subclass of a contract-allowed exception is allowed (LSP-compliant).
 * - Return type covariance (child return type must be equal or more specific than the contract return type)
 * - Parameter type contravariance (child parameter types must be equal or wider than contract parameter types)
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
            $violations = array_merge(
                $violations,
                $this->checkReturnTypeCovarianceViolations($class, $classMethod, $contract, $contractMethod)
            );
            $violations = array_merge(
                $violations,
                $this->checkParameterTypeContravarianceViolations($class, $classMethod, $contract, $contractMethod)
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

        // Get use imports for proper FQCN resolution of docblock @throws short names
        $classUseImports = $this->throwsDetector->getUseImportsForClass($class);
        $contractUseImports = $this->throwsDetector->getUseImportsForClass($contract);

        // Violation if the class DECLARES throws not allowed by the contract (strict or subclass)
        foreach ($classThrowsDeclared as $exceptionType) {
            if ($this->isExceptionAllowedByContract($exceptionType, $contractThrows, $class, $contract, $classUseImports, $contractUseImports)) {
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
            if ($this->isExceptionAllowedByContract($exceptionType, $contractThrows, $class, $contract, $classUseImports, $contractUseImports)) {
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
     * Check if class method return type is covariant with contract method return type.
     *
     * @return LspViolation[]
     */
    private function checkReturnTypeCovarianceViolations(
        ReflectionClass $class,
        ReflectionMethod $classMethod,
        ReflectionClass $contract,
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
                    $this->typeToString($classReturnType),
                    $this->typeToString($contractReturnType),
                ),
            ),
        ];
    }

    /**
     * Check if class method parameter types are contravariant with contract method parameter types.
     *
     * Contravariance means each parameter in the implementation must accept at least everything
     * the contract parameter accepts (same or wider type). Strengthening preconditions is a violation.
     *
     * @return LspViolation[]
     */
    private function checkParameterTypeContravarianceViolations(
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
                    $this->typeToString($classParamType),
                    $this->typeToString($contractParamType),
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
    private function isParameterTypeContravariant(
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
        return $this->isTypeSubtypeOf($contractParamType, $classParamType, $contractContext, $classContext);
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

        return $this->isTypeSubtypeOf($classReturnType, $contractReturnType, $classContext, $contractContext);
    }

    private function isTypeSubtypeOf(
        ReflectionType $childType,
        ReflectionType $parentType,
        ReflectionClass $childContext,
        ReflectionClass $parentContext,
    ): bool {
        if ($childType instanceof ReflectionUnionType) {
            foreach ($childType->getTypes() as $childPart) {
                if (!$this->isTypeSubtypeOf($childPart, $parentType, $childContext, $parentContext)) {
                    return false;
                }
            }
            return true;
        }

        if ($parentType instanceof ReflectionUnionType) {
            foreach ($parentType->getTypes() as $parentPart) {
                if ($this->isTypeSubtypeOf($childType, $parentPart, $childContext, $parentContext)) {
                    return true;
                }
            }
            return false;
        }

        if ($childType instanceof ReflectionIntersectionType) {
            foreach ($childType->getTypes() as $childPart) {
                if ($this->isTypeSubtypeOf($childPart, $parentType, $childContext, $parentContext)) {
                    return true;
                }
            }
            return false;
        }

        if ($parentType instanceof ReflectionIntersectionType) {
            foreach ($parentType->getTypes() as $parentPart) {
                if (!$this->isTypeSubtypeOf($childType, $parentPart, $childContext, $parentContext)) {
                    return false;
                }
            }
            return true;
        }

        if (!$childType instanceof ReflectionNamedType || !$parentType instanceof ReflectionNamedType) {
            return false;
        }

        return $this->isNamedTypeSubtypeOf($childType, $parentType, $childContext, $parentContext);
    }

    private function isNamedTypeSubtypeOf(
        ReflectionNamedType $childType,
        ReflectionNamedType $parentType,
        ReflectionClass $childContext,
        ReflectionClass $parentContext,
    ): bool {
        $childName = $this->normalizeTypeName($childType->getName(), $childContext);
        $parentName = $this->normalizeTypeName($parentType->getName(), $parentContext);

        if ($childType->allowsNull() && $childName !== 'mixed' && $childName !== 'null' && !$this->typeAllowsNull($parentType)) {
            return false;
        }

        if ($childName === $parentName) {
            return true;
        }

        if ($childName === 'never') {
            return true;
        }

        if ($parentName === 'mixed') {
            return true;
        }

        if ($childName === 'null') {
            return $this->typeAllowsNull($parentType);
        }

        if ($parentName === 'null') {
            return $childName === 'null';
        }

        if ($parentName === 'bool' && ($childName === 'true' || $childName === 'false')) {
            return true;
        }

        if ($parentName === 'iterable') {
            return $childName === 'array' || $this->isResolvedTypeSubtypeOf($childName, 'Traversable');
        }

        if ($parentName === 'object') {
            return !$this->isBuiltinTypeName($childName);
        }

        if ($childName === 'void' || $parentName === 'void') {
            return false;
        }

        if ($this->isBuiltinTypeName($childName) || $this->isBuiltinTypeName($parentName)) {
            return false;
        }

        return $this->isResolvedTypeSubtypeOf($childName, $parentName);
    }

    private function isResolvedTypeSubtypeOf(string $childType, string $parentType): bool
    {
        if ($childType === $parentType) {
            return true;
        }

        if ((!class_exists($childType) && !interface_exists($childType))
            || (!class_exists($parentType) && !interface_exists($parentType))) {
            return false;
        }

        return is_a($childType, $parentType, true);
    }

    private function normalizeTypeName(string $typeName, ReflectionClass $context): string
    {
        $type = ltrim($typeName, '\\');
        $lowerType = strtolower($type);

        return match ($lowerType) {
            'self', 'static' => $context->getName(),
            'parent' => $context->getParentClass() ? $context->getParentClass()->getName() : 'parent',
            default => $lowerType,
        };
    }

    private function typeAllowsNull(ReflectionNamedType $type): bool
    {
        return $type->allowsNull() || strtolower($type->getName()) === 'mixed';
    }

    private function isBuiltinTypeName(string $typeName): bool
    {
        return in_array(strtolower($typeName), [
            'array',
            'bool',
            'callable',
            'false',
            'float',
            'int',
            'iterable',
            'mixed',
            'never',
            'null',
            'object',
            'parent',
            'resource',
            'self',
            'static',
            'string',
            'true',
            'void',
        ], true);
    }

    private function typeToString(?ReflectionType $type): string
    {
        if ($type === null) {
            return 'none';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            if ($type->allowsNull() && strtolower($name) !== 'mixed' && strtolower($name) !== 'null') {
                return '?' . $name;
            }
            return $name;
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                fn(ReflectionType $unionType): string => $this->typeToString($unionType),
                $type->getTypes(),
            ));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map(
                fn(ReflectionType $intersectionType): string => $this->typeToString($intersectionType),
                $type->getTypes(),
            ));
        }

        return (string) $type;
    }

    /**
     * Resolve an exception type name to its FQCN using use imports, class namespace,
     * and global namespace as fallback.
     *
     * Resolution order (matching PHP's own name resolution rules):
     * 1. Multi-segment name (contains \) → already a namespace path, treat as FQCN
     * 2. Use imports → if the short name matches a `use` import, resolve to its FQCN
     * 3. Current namespace → if a class with that name exists in the same namespace
     * 4. Global namespace → fallback for standard PHP exceptions (Exception, RuntimeException, etc.)
     *
     * @param array<string, string> $useImports Short name → FQCN map from `use` statements
     */
    private function resolveExceptionFqcn(string $type, ReflectionClass $class, array $useImports = []): string
    {
        $type = ltrim($type, '\\');
        // Multi-segment name (e.g. "Foo\BarException") → already a namespace path, treat as FQCN
        if (str_contains($type, '\\')) {
            return '\\' . $type;
        }
        // Check use imports (handles short names from docblocks like @throws MyException
        // when there is a `use Some\Namespace\MyException;` in the file)
        if (isset($useImports[$type])) {
            return '\\' . ltrim($useImports[$type], '\\');
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
     *
     * @param array<string, string> $classUseImports    Use imports from the class file
     * @param array<string, string> $contractUseImports Use imports from the contract file
     */
    private function isExceptionAllowedByContract(
        string $thrownType,
        array $contractThrows,
        ReflectionClass $class,
        ReflectionClass $contract,
        array $classUseImports = [],
        array $contractUseImports = [],
    ): bool {
        if (empty($contractThrows)) {
            return false;
        }
        $thrownFqcn = $this->resolveExceptionFqcn($thrownType, $class, $classUseImports);
        foreach ($contractThrows as $contractType) {
            $contractFqcn = $this->resolveExceptionFqcn($contractType, $contract, $contractUseImports);
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