# Changelog

## v1.0.3 - 2025-09-18

### What's New

This release handles the following:

- Implements silent handling of the same state transitions in StateManager
- Dynamically sets the model field instead of using `setAttribute`

**Full Changelog**: https://github.com/nthndnn/laravel-state-history/compare/v1.0.2...v1.0.3

## v1.0.2 - 2025-08-25

### What's New

This release introduces significant improvements to the package naming and structure for better clarity and Laravel conventions.

- **Improved Naming Convention:** Renamed core model from ModelState to StateHistory for better clarity
- **Better Table Naming:** Migration now creates state_histories table instead of model_states
- **Enhanced Performance:** All string concatenations replaced with sprintf for better performance
- **Cleaner Codebase:** Improved code consistency and maintainability

**Full Changelog**: https://github.com/nthndnn/laravel-state-history/compare/v1.0.1...v1.0.2

## v1.0.1 - 2025-08-24

### What's Changed

* Bump actions/checkout from 4 to 5 by @dependabot[bot] in https://github.com/nthndnn/laravel-state-history/pull/1

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/nthndnn/laravel-state-history/pull/1

**Full Changelog**: https://github.com/nthndnn/laravel-state-history/compare/v1.0.0...v1.0.1
