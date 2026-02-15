<?php

declare(strict_types=1);

namespace Tivins\Solid\LSP;

use ReflectionClass;
use ReflectionMethod;

/**
 * Checks if a class method declares or actually throws exceptions not allowed by the contract.
 *
 * Two types of violations are detected:
 * - Docblock violations: @throws declarations not present in the contract
 * - Code violations: actual throw statements (AST) for exceptions not in the contract
 */
readonly class ThrowsContractRuleChecker implements LspRuleCheckerInterface
{
    public function __construct(private ThrowsDetectorInterface $throwsDetector)
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

        $contractThrows = $this->throwsDetector->getDeclaredThrows($contractMethod);
        $classThrowsDeclared = $this->throwsDetector->getDeclaredThrows($classMethod);
        $classThrowsWithChains = $this->throwsDetector->getActualThrowsWithChains($classMethod);

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
        foreach ($classThrowsWithChains as $item) {
            $exceptionType = $item['exception'];
            $chains = $item['chains'];
            if ($this->isExceptionAllowedByContract($exceptionType, $contractThrows, $class, $contract, $classUseImports, $contractUseImports)) {
                continue;
            }
            $details = $this->formatCallChains($chains);
            $violations[] = new LspViolation(
                className: $class->getName(),
                methodName: $classMethod->getName(),
                contractName: $contract->getName(),
                reason: sprintf(
                    'throws %s in code (detected via AST) but not allowed by the contract',
                    $exceptionType,
                ),
                details: $details,
            );
        }

        return $violations;
    }

    /**
     * Format call chains for violation details (e.g. "Call chain 1: A::a → B::b → C::c").
     *
     * @param list<string[]> $chains Each chain is an ordered list of "ClassName::methodName" steps
     */
    private function formatCallChains(array $chains): ?string
    {
        if ($chains === []) {
            return null;
        }
        $lines = [];
        foreach ($chains as $i => $chain) {
            $step = implode(' → ', $chain);
            $lines[] = (count($chains) > 1 ? 'Call chain ' . ($i + 1) . ': ' : 'Call chain: ') . $step;
        }
        return implode("\n", $lines);
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
        string          $thrownType,
        array           $contractThrows,
        ReflectionClass $class,
        ReflectionClass $contract,
        array           $classUseImports = [],
        array           $contractUseImports = [],
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
