# Changelog

## [Unreleased]

## [0.8.0] - 2026-02-13

### Added
- **Parameter type contravariance check** — `LiskovSubstitutionPrincipleChecker` now validates that overriding methods keep contravariant parameter types (same or wider) with respect to interface/parent contracts. Strengthening a precondition (narrowing a parameter type) is reported as an LSP violation.
- **Contravariance examples and tests** — Added `MyInterface7`/`MyClass7` (valid widening from `RuntimeException` to `Exception`), `MyInterface8`/`MyClass8` (identical types), and direct unit tests for the contravariance logic (violation detection on narrowed types, untyped parameter handling).

## [0.7.0] - 2026-02-13

### Added
- **Return type covariance check** — `LiskovSubstitutionPrincipleChecker` now validates that overriding methods keep a covariant return type with respect to interface/parent contracts and reports an explicit LSP violation when incompatible.
- **Covariance example and test** — Added `MyInterface6`/`MyClass6` example plus PHPUnit coverage to ensure a narrower return type in the implementation is accepted.

## [0.6.0] - 2026-02-13

### Fixed
- **Docblock `@throws` with short names via `use` import** — The checker now correctly resolves short exception names in `@throws` tags (e.g. `@throws InvalidEntityTypeException`) when the exception class is imported via a `use` statement. Previously, only FQCN and global PHP exceptions were resolved correctly, causing false positives for custom exceptions referenced by their short name in interface/parent contracts.

### Added
- `ThrowsDetector::getUseImportsForClass()` — Extracts the `use` import map (short name → FQCN) from the AST of the file containing a class or interface. Used internally to resolve docblock `@throws` short names.
- **Namespace resolution test scenarios 14–15** — Contract uses short custom exception name via `use` import (exact match and subclass hierarchy).
- **ThrowsDetector unit tests** for `getUseImportsForClass()` (import map extraction, namespace isolation).

## [0.5.0] - 2026-02-13

### Added
- **CLI integration tests** (`tests/CliIntegrationTest.php`) — Exit code (0/1/2), usage and error messages on stderr, JSON output structure (`violations`, `errors`), violation object shape (`className`, `methodName`, `contractName`, `reason`), and `--quiet` behaviour.
- **ThrowsDetector unit tests** (`tests/ThrowsDetectorTest.php`) — Docblock parsing (no docblock, single @throws, FQCN, pipe-separated, description text), AST detection (direct throw, rethrow in catch, transitive private method), and normalized/unique return values.
- **ClassFinder unit tests** (`tests/ClassFinderTest.php`) — Invalid directory (InvalidArgumentException), empty directory, one-class directory, implicit exclusion of PHP files without classes, sorted result, and autoload inclusion when scanning under project.

## [0.4.0] - 2026-02-13

### Added
- **Exception hierarchy** — A thrown exception type is now allowed by the contract if it is the same as or a **subclass** of any exception declared in the contract (LSP-compliant). Example: contract `@throws RuntimeException`, implementation throws `UnexpectedValueException` → no violation. This removes false positives when implementations throw more specific exception types.

## [0.3.5] - 2026-02-13

### Fixed
- **Binary** — In `lsp-checker`, the exception message when `vendor/autoload.php` is not found now uses the correct PHP constant `__DIR__` instead of `__dir__` (which was displayed literally).

## [0.3.4] - 2026-02-13

### Added
- **JSON report** — Load/reflection errors are now included in `--json` output under an `errors` key. The report shape is `{ "violations": [...], "errors": [{ "class": "...", "message": "..." }] }`.

### Added
- **GitHub Actions CI** — workflow `.github/workflows/ci.yml` runs PHPUnit on push/PR for PHP 8.2, 8.3, 8.4
- **PHPUnit tests** — `tests/LiskovSubstitutionPrincipleCheckerTest.php` uses the example classes (MyClass1–MyClass5) to assert expected LSP violations and passes
- `phpunit.xml.dist` for test configuration

### Changed
- **CLI** — `<directory>` is now required; without arguments the script prints usage and exits with code 2
- Removed `--example` option; the built-in example is only used by unit tests (`vendor/bin/phpunit`)

## [0.3.3] - 2026-02-13

### Fixed
- **CLI** — When a class triggers a `ReflectionException` (e.g. not loadable), the error is no longer mixed with LSP violations: it is shown as a single "Error: …" line, not added to the violation count, and excluded from the `--json` output so the report stays an array of violations only.

## [0.3.2] - 2026-02-12

### Changed
- **CLI binary** — entry point renamed from `lsp-checker.php` to `lsp-checker` (no extension); use `vendor/bin/lsp-checker <options>` after `composer require tivins/poc-liskov-check`
- Autoload path now works both when run from package root and when installed as a dependency (`dirname(__DIR__) . '/autoload.php'` when in `vendor/bin`)
- Fixed `__DIR__` constant (was incorrectly `__dir__`)

## [0.3.1] - 2026-02-12

### Fixed
- **CI PHP 8.2** — PHPUnit contraint à `^11.0` (compatible PHP 8.2) au lieu de `12.5.x-dev` (PHP ≥ 8.3) pour que le workflow GitHub Actions passe sur la matrice PHP 8.2, 8.3, 8.4

## [0.3.0] - 2026-02-12

### Added
- **Directory scanning** — pass a directory path as argument to recursively discover and check all PHP classes (`ClassFinder`)
- `--example` CLI option to explicitly run the built-in example
- `ClassFinder` class (`src/Process/ClassFinder.php`) — uses php-parser to extract FQCN from PHP files; auto-detects `vendor/autoload.php` in the target project

### Changed
- `lsp-checker` (formerly `lsp-checker.php`) now accepts `<path>` as first positional argument; without arguments, the built-in example still runs (backward-compatible)

## [0.2.0] - 2026-02-12

### Added
- `ThrowsDetector::getActualThrows()` — detects exceptions actually thrown in method bodies via AST analysis (`nikic/php-parser`)
  - Handles `throw new ClassName()` (direct throws)
  - Handles re-throws in catch blocks (`catch (E $e) { throw $e; }`)
  - Handles conditional throws (`if (...) throw new E()`)
  - Follows `$this->method()` calls recursively within the same class (transitive throw detection)
  - Circular call protection to prevent infinite recursion
  - Full FQCN resolution via `NameResolver` (supports `use` statements and namespaces)
- Internal file AST cache in `ThrowsDetector` to avoid re-parsing the same file
- New example `MyClass4` — class with throw in code but no `@throws` docblock (AST-only detection)
- New example `MyClass5` — class with throw via private method delegation (transitive AST detection)

### Changed
- `LiskovSubstitutionPrincipleChecker::checkThrowsViolations()` now checks both docblock `@throws` and actual throw statements (AST)
- Violation messages now distinguish between docblock violations and code (AST) violations

## [0.1.0] - 2026-02-12

### Added
- `ThrowsDetector` class to parse `@throws` declarations from docblocks
- `LspViolation` value object for structured violation reporting
- `LiskovSubstitutionPrincipleChecker` with full exception contract checking
  - Checks against all implemented interfaces
  - Checks against parent class
  - Detects `@throws` declarations not allowed by the contract
- Entry point script `lsp-checker` (formerly `lsp-checker.php`) with colored pass/fail output
- Example violation classes for testing (`liskov-principles-violation-example.php`)
