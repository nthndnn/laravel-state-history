# Changelog

## v1.0.6 - 2026-04-11

### What's Changed

* Add Laravel 13 support
* Bump actions/upload-artifact from 6 to 7 by @dependabot[bot] in https://github.com/nthndnn/laravel-state-history/pull/11

**Full Changelog**: https://github.com/nthndnn/laravel-state-history/compare/v1.0.5...v1.0.6

## v1.0.5 - 2026-02-08

* Add fallback support to StateManager for state transitions
* Introduce StateTransitionFailedException for better error handling

**Full Changelog**: https://github.com/nthndnn/laravel-state-history/compare/v1.0.4...v1.0.5

## v1.0.4 - 2026-02-08

### What's Changed

* Bump actions/github-script from 7 to 8 by @dependabot[bot] in https://github.com/nthndnn/laravel-state-history/pull/2
* Bump stefanzweifel/git-auto-commit-action from 6 to 7 by @dependabot[bot] in https://github.com/nthndnn/laravel-state-history/pull/4
* Bump actions/checkout from 5 to 6 by @dependabot[bot] in https://github.com/nthndnn/laravel-state-history/pull/6
* Bump actions/upload-artifact from 4 to 6 by @dependabot[bot] in https://github.com/nthndnn/laravel-state-history/pull/8
* Bump laravel/pint from 1.24.0 to 1.27.0 by @dependabot[bot] in https://github.com/nthndnn/laravel-state-history/pull/9
* Add fallback support to StateManager for state transitions
* Introduce StateTransitionFailedException for better error handling

**Full Changelog**: https://github.com/nthndnn/laravel-state-history/compare/v1.0.3...v1.0.4

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
