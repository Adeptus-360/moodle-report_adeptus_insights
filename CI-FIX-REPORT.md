# CI Fix Report — Adeptus Insights Plugin

**Date:** 2026-02-19
**Commit:** `942a56e` — `fix(CI): resolve lint and coding standard violations from G2-G9 features`
**Pushed to:** GitLab (bccc) only

## Issues Found & Fixed

### 1. GPL Boilerplate — Trailing Periods (91 files)
Every PHP file had spurious trailing periods on GPL comment lines, e.g.:
```
// Moodle is free software: you can redistribute it and/or modify.
```
Should be:
```
// Moodle is free software: you can redistribute it and/or modify
```
This violates `moodle-plugin-ci phpcs` (Moodle coding standard expects exact GPL text).

**Fix:** Automated replacement across all 91 PHP files.

### 2. Missing `defined('MOODLE_INTERNAL') || die()` (2 files)
- `db/uninstall.php` — non-namespaced file, required the guard
- `classes/branding_manager.php` — had namespace but was missing the guard after namespace declaration

### 3. Mustache Template Docblock (1 file)
- `templates/alert_digest_email.mustache` — missing `@package`, `@copyright`, `@license` in the template comment block

## Verification
- `php -l` on all PHP files: **0 syntax errors**
- No tabs, no trailing whitespace, all files end with newline
- No missing lang strings (all `get_string()` calls reference core or defined plugin strings)
- AMD build files present and matching src files
- No new JS files requiring grunt build

## CI Checks Covered
| Check | Status |
|-------|--------|
| PHP Lint | ✅ Clean |
| PHPCS (boilerplate) | ✅ Fixed |
| PHPDoc (MOODLE_INTERNAL) | ✅ Fixed |
| Mustache Lint | ✅ Fixed |
| Validate | ✅ Expected pass |
