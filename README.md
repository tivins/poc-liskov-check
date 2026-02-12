# poc-liskov-check

A PHP proof-of-concept that detects **Liskov Substitution Principle (LSP)** violations, focusing on exception contracts between classes and their contracts (interfaces and parent classes).

## What it checks

A subclass or implementation must not weaken the contract of its parent or interface. This POC verifies that **exception contracts** are respected:

- A method must not **declare** (in docblocks) or **throw** (in code) exception types that are not allowed by the contract (interface or parent class).
- If the contract says nothing about exceptions, the implementation must not throw (or declare) any.
- If the contract documents `@throws SomeException`, the implementation may throw that type.

Violations are reported in two ways:

1. **Docblock violations** — `@throws` in the implementation that are not in the contract.
2. **Code violations** — actual `throw` statements (detected via AST) for exception types not allowed by the contract.

## Features

- **Docblock analysis** — parses `@throws` from PHPDoc (supports piped types, FQCN, descriptions).
- **AST analysis** — uses [nikic/php-parser](https://github.com/nikic/PHP-Parser) to detect real `throw` statements:
  - Direct throws: `throw new RuntimeException()`
  - Conditional throws: `if (...) throw new E()`
  - Re-throws in catch: `catch (E $e) { throw $e; }`
  - **Transitive throws** — follows `$this->method()` calls within the same class (e.g. public method calling a private method that throws).
- **Contract comparison** — checks against all implemented interfaces and the parent class.
- **Cached parsing** — each file is parsed once; results are reused for multiple methods.

## Requirements

- PHP 8.2+
- Composer

## Installation

```bash
git clone <repo-url>
cd poc-liskov-check
composer install
```

## Usage

1. Ensure your classes (and their contracts) are loadable (e.g. via autoload or `require`).
2. Run the checker and pass the classes to check (see `lsp-checker.php` for the current setup):

```bash
php lsp-checker.php
```

Example output:

```
Checking Liskov Substitution Principle...

[FAIL] MyClass1
       -> MyClass1::doSomething() — contract MyInterface1 — @throws RuntimeException declared in docblock but not allowed by the contract
       -> MyClass1::doSomething() — contract MyInterface1 — throws RuntimeException in code (detected via AST) but not allowed by the contract
[PASS] MyClass2
[FAIL] MyClass3
       ...
[FAIL] MyClass4
       -> MyClass4::process() — contract MyInterface4 — throws RuntimeException in code (detected via AST) but not allowed by the contract
[FAIL] MyClass5
       -> MyClass5::process() — contract MyInterface5 — throws RuntimeException in code (detected via AST) but not allowed by the contract

Classes checked: 5
Passed: 1 / 5
Total violations: 8
```

- **Exit code**: `0` if all classes pass, `1` if any violation is found (suitable for CI).
- **JSON report**: Full list of violations is written to stderr in JSON format.

## Architecture

```
src/LSP/
├── ThrowsDetector.php                      # Extracts @throws from docblocks; detects actual throws via AST
├── LiskovSubstitutionPrincipleChecker.php  # Orchestrates checks against interfaces and parent class
└── LspViolation.php                        # Value object (className, methodName, contractName, reason)

lsp-checker.php                              # CLI entry point
liskov-principles-violation-example.php      # Example classes (MyClass1–MyClass5) for testing
```

- **ThrowsDetector**  
  - `getDeclaredThrows(ReflectionMethod)` — returns exception types from `@throws` in the docblock.  
  - `getActualThrows(ReflectionMethod)` — returns exception types from the method body (and transitively from `$this->method()` in the same class) via AST.

- **LiskovSubstitutionPrincipleChecker**  
  - `check(string $className)` — returns `LspViolation[]`.  
  - For each method that overrides/implements a contract method, compares both declared and actual throws to the contract and reports any that are not allowed.

## Example cases (included)

| Class    | Contract      | Result | Reason |
|----------|---------------|--------|--------|
| MyClass1 | No throws     | FAIL   | Throws and declares `RuntimeException` |
| MyClass2 | `@throws RuntimeException` | PASS | Contract allows the throw |
| MyClass3 | No throws     | FAIL   | Declares and throws via private helper |
| MyClass4 | No throws     | FAIL   | Throws in code, no docblock (AST-only detection) |
| MyClass5 | No throws     | FAIL   | Throws via `$this->doSomething()` (transitive AST) |

## Limitations

- **Intra-class only** — only `$this->method()` calls within the same class are followed; calls to other classes or traits are not analyzed.
- **No flow analysis** — e.g. `$e = new E(); throw $e;` is not resolved (we only handle `throw new X` and re-throws of catch variables).
- **Reflection-based** — only works on loadable PHP code (files that can be parsed and reflected).
- **Exception contracts only** — no checks yet for parameter types (contravariance) or return types (covariance).

## License

MIT.
