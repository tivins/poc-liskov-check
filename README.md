# php-liskov - LSP violations detector

A PHP tool that detects **Liskov Substitution Principle (LSP)** violations, focusing on exception contracts, return type covariance, and parameter type contravariance between classes and their contracts (interfaces and parent classes).


[![CI](https://github.com/tivins/php-liskov/actions/workflows/ci.yml/badge.svg)](https://github.com/tivins/php-liskov/actions/workflows/ci.yml)


## What it checks

```php
interface MyInterface1
{
    /**
     * This method does not mentions throw an exception. Subclasses must not throw any exceptions.
     */
    public function doSomething(): void;
}

/**
 * This class violates the Liskov Substitution Principle.
 */
class MyClass1 implements MyInterface1
{
    /**
     * This method throws an exception, which violates the Liskov Substitution Principle.
     * The subclass should not throw an exception if the parent class does not throw an exception.
     */
    public function doSomething(): void
    {
        throw new RuntimeException("exception is thrown");
    }
}
```


A subclass or implementation must not weaken the contract of its parent or interface. Liskov verifies:

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
  - **Cross-class static calls** — follows `ClassName::method()` static calls to detect exceptions thrown transitively in external classes.
  - **Cross-class instance calls** — follows `(new ClassName())->method()` calls to detect exceptions thrown by methods on newly created objects.
  - **Dynamic method calls on variables** — follows `$variable->method()` when the variable type is known: from parameter type hints (e.g. `function doSomething(Helper $helper)`) or from local assignments `$var = new ClassName();` (simple flow). Union types on parameters are supported (all class types are followed).
- **Contract comparison** — checks against all implemented interfaces and the parent class.
- **Return type covariance** — validates that overriding methods keep LSP-compliant covariant return types.
- **Parameter type contravariance** — validates that overriding methods do not narrow parameter types (preconditions must not be strengthened).
- **Cached parsing** — each file is parsed once; results are reused for multiple methods.

## Requirements

- PHP 8.2+
- Composer

## Installation

```bash
composer require tivins/php-liskov
```

## Usage

You can run the checker in two ways: by passing a directory, or by using a configuration file.

### Scan a directory

Pass a directory path as the first argument. The checker builds a `Config` with that directory and recursively finds all PHP classes to check:

```bash
vendor/bin/lsp-checker src/
```

The classes (and their contracts — interfaces, parent classes) must be loadable. If a `vendor/autoload.php` is found in or near the target directory, it is included automatically.

### Configuration file

Use `--config <file>` to load a PHP file that **returns** a `Tivins\LSP\Config` instance. The config defines which directories and files to scan, and optional exclusions:

```bash
vendor/bin/lsp-checker --config lsp-config.php
```

Example config file (e.g. `lsp-config.php`):

```php
<?php

declare(strict_types=1);

use Tivins\LSP\Config;

return (new Config())
    ->addDirectory('path/to/folder')
    ->excludeDirectory('path/to/folder/excluded')
    ->addFile('path/to/file')
    ->excludeFile('path/to/excluded/file');
```

- **`addDirectory($path)`** — Recursively scan a directory for PHP classes.
- **`addFile($path)`** — Include a single PHP file.
- **`excludeDirectory($path)`** — Skip that directory and its contents when scanning.
- **`excludeFile($path)`** — Skip that file even if it would be included by a directory.

Without a directory and without `--config`, the script prints usage and exits:

```bash
vendor/bin/lsp-checker
# Usage: lsp-checker <directory> [--config <file>] [--json] [--quiet]
#        lsp-checker --config <file> [--json] [--quiet]
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
- **stderr** — Progress and summary messages ("Checking…", "Classes checked: …", etc.). Suppressed with `--quiet`.

So you can capture only the result in a file and keep logs separate.

### Options

| Option           | Description |
|------------------|-------------|
| `<directory>`    | Directory to scan. **Required** when not using `--config`. |
| `--config <file>` | Path to a PHP file that returns a `Tivins\LSP\Config` instance. When present, `<directory>` is not required. |
| `--quiet`        | Suppress progress and summary on stderr. Only the result (stdout) is produced — useful for CI or when piping. |
| `--json`         | Machine-readable output: write only the JSON report to stdout; no [PASS]/[FAIL] lines. |

### Pipes and redirections

| Goal | Command |
|------|--------|
| Save JSON report to a file | `vendor/bin/lsp-checker src/ --json > report.json` |
| Save human result, hide progress | `vendor/bin/lsp-checker src/ --quiet > result.txt` |
| Save progress/summary to a log | `vendor/bin/lsp-checker src/ 2> progress.log` (result stays on terminal) |
| JSON only, no progress (e.g. CI) | `vendor/bin/lsp-checker src/ --json --quiet 2>/dev/null` |
| Result to file, progress to another file | `vendor/bin/lsp-checker src/ --json > report.json 2> progress.log` |
| Use a config file | `vendor/bin/lsp-checker --config lsp-config.php` |

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

## Limitations

- **Limited dynamic call resolution** — `$variable->method()` calls are followed only when the variable type can be statically resolved: parameter type hints (e.g. `function doSomething(Helper $helper)`) and simple local assignments (`$var = new ClassName()`). Dynamic calls where the variable type cannot be determined (e.g. untyped parameter, factory return, or complex control flow) are not followed. Trait methods used via `use SomeTrait` are analyzed, but `$this->method()` calls within a trait body are not resolved to the using class.
- **No flow analysis** — e.g. `$e = new E(); throw $e;` is not resolved (we only handle `throw new X` and re-throws of catch variables).
- **Reflection-based** — only works on loadable PHP code (files that can be parsed and reflected). When scanning, a `vendor/autoload.php` is loaded automatically if found in or near the target paths.
- **Parameter contravariance via Reflection only** — parameter type contravariance is checked on loaded classes. Since PHP itself enforces parameter compatibility at class load time, most violations are caught by the engine before the checker runs. The check is still useful as part of a comprehensive LSP report.

## License

MIT.
