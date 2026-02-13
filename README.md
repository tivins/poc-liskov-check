# PoC - LSP violations detector

A PHP proof-of-concept that detects **Liskov Substitution Principle (LSP)** violations, focusing on exception contracts, return type covariance, and parameter type contravariance between classes and their contracts (interfaces and parent classes).


[![CI](https://github.com/tivins/poc-liskov-check/actions/workflows/ci.yml/badge.svg)](https://github.com/tivins/poc-liskov-check/actions/workflows/ci.yml)


## What it checks

A subclass or implementation must not weaken the contract of its parent or interface. This POC verifies:

- A method must not **declare** (in docblocks) or **throw** (in code) exception types that are not allowed by the contract (interface or parent class).
- If the contract says nothing about exceptions, the implementation must not throw (or declare) any.
- If the contract documents `@throws SomeException`, the implementation may throw that type or any **subclass** (exception hierarchy is respected; e.g. contract `@throws RuntimeException` allows throwing `UnexpectedValueException`).
- A method return type must be **covariant** with the contract return type (same type or more specific subtype).
- A method parameter type must be **contravariant** with the contract parameter type (same type or wider supertype). Narrowing a parameter type strengthens the precondition and is a violation.

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
- **Return type covariance** — validates that overriding methods keep LSP-compliant covariant return types.
- **Parameter type contravariance** — validates that overriding methods do not narrow parameter types (preconditions must not be strengthened).
- **Cached parsing** — each file is parsed once; results are reused for multiple methods.

## Requirements

- PHP 8.2+
- Composer

## Installation

```bash
composer require tivins/poc-liskov-check
```

## Usage

### Scan a directory

Pass a directory path as argument. The checker will recursively find all PHP classes and check them:

```bash
vendor/bin/lsp-checker src/
```

The classes (and their contracts — interfaces, parent classes) must be loadable. If a `vendor/autoload.php` is found in or near the target directory, it is included automatically.

Without a directory, the script prints usage and exits:

```bash
vendor/bin/lsp-checker
# Usage: lsp-checker <directory> [--json] [--quiet]
#   ...
```

### Run unit tests

The example classes in `liskov-principles-violation-example.php` are used by PHPUnit tests:

```bash
composer install
composer test # or vendor/bin/phpunit
```

### Output streams (stdout / stderr)

- **stdout** — Program result only: either human-readable [PASS]/[FAIL] lines (default) or a single JSON report when `--json` is used. Safe to redirect or pipe (e.g. `> out.json`).
- **stderr** — Progress and summary messages (“Checking…”, “Classes checked: …”, etc.). Suppressed with `--quiet`.

So you can capture only the result in a file and keep logs separate.

### Options

| Option    | Description |
|-----------|-------------|
| `<path>`  | **Required.** Directory to scan recursively for PHP classes. |
| `--quiet` | Suppress progress and summary on stderr. Only the result (stdout) is produced — useful for CI or when piping. |
| `--json`  | Machine-readable output: write only the JSON report to stdout; no [PASS]/[FAIL] lines. |

### Pipes and redirections

| Goal | Command |
|------|--------|
| Save JSON report to a file | `vendor/bin/lsp-checker src/ --json > report.json` |
| Save human result, hide progress | `vendor/bin/lsp-checker src/ --quiet > result.txt` |
| Save progress/summary to a log | `vendor/bin/lsp-checker src/ 2> progress.log` (result stays on terminal) |
| JSON only, no progress (e.g. CI) | `vendor/bin/lsp-checker src/ --json --quiet 2>/dev/null` |
| Result to file, progress to another file | `vendor/bin/lsp-checker src/ --json > report.json 2> progress.log` |

To pipe the JSON into another tool (e.g. [jq](https://jqlang.github.io/jq/)), use `--json --quiet` so only JSON goes to stdout:

```bash
vendor/bin/lsp-checker src/ --json --quiet | jq '.violations | length'
```

The JSON report is an object with two keys:
- **`violations`** — array of LSP violations (each with `className`, `methodName`, `contractName`, `reason`).
- **`errors`** — array of load/reflection errors (each with `class`, `message`) for classes that could not be checked.

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

- **Exit code**: `0` if all classes pass, `1` if any violation or load error is found (suitable for CI).
- **JSON report**: Use `--json` to write a report to stdout: `{ "violations": [...], "errors": [...] }`.

## Architecture

```
src/
├── LSP/
│   ├── ThrowsDetector.php                      # Extracts @throws from docblocks; detects actual throws via AST
│   ├── LiskovSubstitutionPrincipleChecker.php  # Orchestrates checks against interfaces and parent class
│   └── LspViolation.php                        # Value object (className, methodName, contractName, reason)
└── Process/
    ├── ClassFinder.php                         # Recursively scans a directory for PHP classes (via AST)
    ├── FormatType.php                          # Output format enum (TEXT / JSON)
    └── StdWriter.php                           # stdout / stderr writer with format filtering

lsp-checker                                  # CLI entry point
liskov-principles-violation-example.php      # Example classes (MyClass1–MyClass8), used by PHPUnit tests
```

- **ThrowsDetector**  
  - `getDeclaredThrows(ReflectionMethod)` — returns exception types from `@throws` in the docblock.  
  - `getActualThrows(ReflectionMethod)` — returns exception types from the method body (and transitively from `$this->method()` in the same class) via AST.

- **LiskovSubstitutionPrincipleChecker**  
  - `check(string $className)` — returns `LspViolation[]`.  
  - For each method that overrides/implements a contract method, compares both declared and actual throws to the contract, checks return type covariance and parameter type contravariance, and reports any violations.

- **ClassFinder**  
  - `findClassesInDirectory(string $directory)` — recursively scans a directory for `.php` files, extracts fully qualified class names via AST, loads the files, and returns the class list.

## Example cases (included)

| Class    | Contract      | Result | Reason |
|----------|---------------|--------|--------|
| MyClass1 | No throws     | FAIL   | Throws and declares `RuntimeException` |
| MyClass2 | `@throws RuntimeException` | PASS | Contract allows the throw |
| MyClass3 | No throws     | FAIL   | Declares and throws via private helper |
| MyClass4 | No throws     | FAIL   | Throws in code, no docblock (AST-only detection) |
| MyClass5 | No throws     | FAIL   | Throws via `$this->doSomething()` (transitive AST) |
| MyClass6 | Return `RuntimeException` | PASS | Returns `UnexpectedValueException` (covariant subtype) |
| MyClass7 | Param `RuntimeException` | PASS | Accepts `Exception` (contravariant supertype) |
| MyClass8 | Params `string`, `int` | PASS | Identical parameter types (trivially valid) |

## Limitations

- **Intra-class only** — only `$this->method()` calls within the same class are followed; calls to other classes or traits are not analyzed.
- **No flow analysis** — e.g. `$e = new E(); throw $e;` is not resolved (we only handle `throw new X` and re-throws of catch variables).
- **Reflection-based** — only works on loadable PHP code (files that can be parsed and reflected). When scanning a directory, a `vendor/autoload.php` is loaded automatically if found nearby.
- **Parameter contravariance via Reflection only** — parameter type contravariance is checked on loaded classes. Since PHP itself enforces parameter compatibility at class load time, most violations are caught by the engine before the checker runs. The check is still useful as part of a comprehensive LSP report.

## License

MIT.
