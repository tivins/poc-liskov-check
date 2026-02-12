# Changelog

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
- Entry point script `lsp-checker.php` with colored pass/fail output
- Example violation classes for testing (`liskov-principles-violation-example.php`)
