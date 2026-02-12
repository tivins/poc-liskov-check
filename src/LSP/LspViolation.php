<?php

declare(strict_types=1);

namespace Tivins\LSP;

/**
 * Represents a single Liskov Substitution Principle violation.
 */
class LspViolation
{
    public function __construct(
        public readonly string $className,
        public readonly string $methodName,
        public readonly string $contractName,
        public readonly string $reason,
    ) {
    }

    public function __toString(): string
    {
        return sprintf(
            '%s::%s() — contract %s — %s',
            $this->className,
            $this->methodName,
            $this->contractName,
            $this->reason,
        );
    }
}
