# Changelog

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
