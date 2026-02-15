<?php

declare(strict_types=1);

namespace Tivins\Solid\LSP;

use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * Handles PHP type comparison, subtyping, and normalization.
 *
 * Responsible for determining whether a type is a subtype of another,
 * handling union/intersection types, built-in types, nullability, and
 * converting types to their string representation.
 */
readonly class TypeSubtypeChecker
{
    /**
     * Check if $childType is a subtype of $parentType, considering
     * union types, intersection types, named types, and nullability.
     */
    public function isTypeSubtypeOf(
        ReflectionType  $childType,
        ReflectionType  $parentType,
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

    /**
     * Convert a ReflectionType to its string representation.
     */
    public function typeToString(?ReflectionType $type): string
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

    private function isNamedTypeSubtypeOf(
        ReflectionNamedType $childType,
        ReflectionNamedType $parentType,
        ReflectionClass     $childContext,
        ReflectionClass     $parentContext,
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
}
