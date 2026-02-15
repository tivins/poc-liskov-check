<?php

declare(strict_types=1);

namespace Tivins\Solid\LSP;

/**
 * Represents a single Liskov Substitution Principle violation.
 */
readonly class LspViolation
{
    public function __construct(
        public string $className,
        public string $methodName,
        public string $contractName,
        public string $reason,
        public ?string $details = null,
    ) {
    }

    public function __toString(): string
    {
        $out = sprintf(
            '%s::%s() — contract %s — %s',
            $this->className,
            $this->methodName,
            $this->contractName,
            $this->reason,
        );
        if ($this->details !== null && $this->details !== '') {
            $out .= "\n         " . str_replace("\n", "\n         ", $this->details);
        }
        return $out;
    }
}
