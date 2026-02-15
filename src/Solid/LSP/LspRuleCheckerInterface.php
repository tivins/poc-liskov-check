<?php

declare(strict_types=1);

namespace Tivins\Solid\LSP;

use ReflectionClass;
use ReflectionMethod;

/**
 * Strategy interface for individual LSP rule checks.
 *
 * Each implementation is responsible for checking a single aspect of the
 * Liskov Substitution Principle (e.g. exception contracts, return type
 * covariance, parameter type contravariance).
 */
interface LspRuleCheckerInterface
{
    /**
     * Check a single class method against its contract method for LSP violations.
     *
     * @return LspViolation[] List of violations found (empty if none)
     */
    public function check(
        ReflectionClass  $class,
        ReflectionMethod $classMethod,
        ReflectionClass  $contract,
        ReflectionMethod $contractMethod,
    ): array;
}
