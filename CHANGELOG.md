# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [1.3.0] - 17/10/2025

### Added
- Added `ObjectViewModeEnum` with three modes to control how objects are traversed:
  - `Copy`: convert objects to stdClass including non-public properties (default, backward compatible)
  - `PublicArray`: build array from public properties only using `get_object_vars` (fast path, no reflection)
  - `Skip`: leave objects as-is without unwrapping
- Added `setObjectViewMode(ObjectViewModeEnum $mode)` and `getObjectViewMode()` methods
- Added traversal limit controls for performance and safety:
  - `setMaxDepth(?int $depth)`: limit recursion depth (default: null = no limit)
  - `setMaxItemsPerContainer(?int $count)`: limit elements per array/container (default: null = no limit)
  - `setMaxTotalNodes(?int $count)`: global cap on visited/processed nodes (default: null = no limit)
- Added `setOnLimitExceededCallback(?callable $cb)` for telemetry on limit/cycle events
- Added `setOverflowPlaceholder(mixed $value)` to configure placeholder for skipped content when limits are hit (default: null = keep original)
- Added cycle detection for objects using `SplObjectStorage` to prevent infinite loops
- Added comprehensive test coverage in `RedactorProcessorLimitsTest` and `RedactorProcessorObjectViewModeTest`

### Changed
- Enhanced `setProcessObjects()` documentation to clarify performance benefits when disabling object processing
- Refactored internal processing logic to support new traversal modes and limits while maintaining full backward compatibility
- All traversal limits are fully opt-in; with defaults (all null), processor behaves exactly as before

### Fixed
- Improved handling of deeply nested structures and large objects with configurable limits


## [1.2.0] - 15/10/2025

### Changed
- Refactored default rules from file-based approach (`default_rules.php`) to class-based implementation (`DefaultRules`) for better organization and type safety
- Updated rule implementations for improved performance and maintainability
- Enhanced test coverage across all rule types

### Fixed
- Fixed issues in various rule implementations to ensure consistent behavior


## [1.1.0] - 14/10/2025

### Added
- Added custom RedactorReflectionException class to handle reflection errors
- Now catching ReflectionException and wrapping it in RedactorReflectionException with more context
- Added explicit dependency on ext-mbstring in composer.json


## [1.0.3] - 13/10/2025

### Added
- Added `setProcessObjects(bool $processObjects)` to control whether objects should be processed (default: true)


## [1.0.2] - 13/10/2025

### Changed
- RedactorProcessor now properly handles UnitEnum values, preserving them without modification
- Improved handling of readonly properties by skipping them instead of throwing errors

### Added
- Added tests for UnitEnum values and readonly properties


## [1.0.1] - 11/10/2025

### Changed
- EmailRule now masks emails by preserving the first 3 characters of the local part and the full domain, inserting a fixed "****" mask between (e.g., "john.doe@example.com" → "joh****@example.com"). For non-email strings, it falls back to generic start/end masking (3 leading, 4 trailing characters visible).

### Added
- Unit tests extending EmailRule coverage, including nested structures and non-email values.


## [1.0.0] - 10/10/2025

### Added
- Initial release of Monolog Redaction Processor.
- `RedactorProcessor` that traverses log context (arrays, objects, iterables) and masks sensitive values.
- Sensible default rules for common sensitive fields (card numbers/PAN, CVV, expiry, names, emails, phone, IPs, addresses, tokens, 3‑D Secure fields, etc.). See `src/Rule/Default/default_rules.php`.
- Built‑in rule types under `Sirix\Monolog\Redaction\Rule`:
  - `StartEndRule($visibleStart, $visibleEnd)`
  - `EmailRule`
  - `PhoneRule`
  - `FullMaskRule`
  - `FixedValueRule($replacement)`
  - `NameRule`
  - `NullRule`
- `RedactionRuleInterface` for custom masking strategies.
- Processor options:
  - `setReplacement(string $char)`
  - `setTemplate(string $template)`
  - `setLengthLimit(?int $limit)`
- PHP 8.1–8.4 support.
- Monolog ^3.0 compatibility.
- PHPUnit test suite and QA tooling (PHP-CS-Fixer, PHPStan, Rector). 
- GitHub Actions workflow for CI.
