# Branch Consolidation Report

**Date:** 2026-03-22
**Performed by:** Kai Nakamura, Managing Director — Adeptus360

---

## Pre-Consolidation Branch State

| Branch | Status | Relationship to main |
|--------|--------|---------------------|
| `main` | G2-G10 features | Base (16 commits ahead of dev) |
| `fix/php82-deprecations` | MOST COMPLETE | 23 commits ahead of main |
| `fix/gh-issues-and-bookmarks` | v2.0 prep | Ancestor of php82 |
| `dev` | Old install fixes | DIVERGED (10 merge conflicts) |
| `fix/bookmark-error` | 1 commit | Bookmark compat fix |
| `fix/contrib-10263-reviewer-feedback-r2` | 5 commits | Plugin Directory CI fixes |
| `production-release` | Empty | Same as old main |

## Step 1: Merges & Cherry-Picks

### ✅ Merged: `fix/php82-deprecations` → `main` (fast-forward)
- **23 commits** merged cleanly (0 conflicts)
- Key changes: v2.0.0 version bump, PHP 8.2 property declarations, jQuery removal, Report Builder UI, cohort/group filters, Reports tab fix, learner dashboard, assistant improvements

### ✅ Cherry-picked from `fix/contrib-10263-reviewer-feedback-r2`:
- **`24a4109`** — Remove self-referencing symlink (`adeptus-insights → /root/clawd/adeptus-insights`)
  - This caused recursive directory scans in CI

### ✅ Manually applied from `dev` (b72ab8d) + `contrib-r2` (0df8222):
- **Hook migration:** Created `db/hooks.php` + `classes/hook/callback/before_http_headers.php` to replace deprecated `report_adeptus_insights_before_http_headers()` function in lib.php
  - Hook callback updated to include full library loading (SweetAlert2 + Simple DataTables + CSS) matching the latest lib.php function
  - Removes deprecation warnings on Moodle 4.5+
- **Forced Boost theme removal:** Removed `$CFG->theme = 'boost'` from 11 PHP files
  - Bad practice — breaks admin's chosen theme
- **install.xml VERSION sync:** Updated from `2026012701` to `2026031601` to match version.php
- **GPL header fix:** Removed trailing periods from 3 files with non-standard headers

### ❌ Skipped from `dev` (with reasons):

| Commit | Description | Reason Skipped |
|--------|-------------|----------------|
| `8688874` | Revert admin_externalpage_setup | Already resolved — main uses manual PAGE setup |
| `4a6a49d` | Restore standard page layout | Already resolved — part of breadcrumb fix chain |
| `9c063a3` | Breadcrumbs 'Preferences' → 'Reports' | Already resolved — settings.php registers under 'reports' |
| `ed0117d` | Lang string {} bug + install redirect | `{$a}` is correct Moodle pattern; redirect flow restructured |
| `2706535` | Redirect to registration on fresh install | Auth flow completely restructured with token_auth_manager |
| `ef946d2` | Rebuild AMD modules with Moodle grunt | Build files have been regenerated multiple times since |

**Note:** The PHP 8.2 property fixes from `b72ab8d` for `installation_manager.php` were already applied in `fix/php82-deprecations` (commit `c38a69c`).

### ❌ Skipped from `fix/bookmark-error`:
| Commit | Description | Reason Skipped |
|--------|-------------|----------------|
| `3559499` | MySQL/PostgreSQL bookmark compat | Already in main via `a384b1a` (fix/php82-deprecations) |

### ❌ Skipped from `fix/contrib-10263-reviewer-feedback-r2`:
| Commit | Description | Reason Skipped |
|--------|-------------|----------------|
| `5963918` | Moodle Code Checker + savepoints | Touches 77 files, mostly GPL header fixes (done), version downgrade to 1.5.0 |
| `0df8222` | Sync install.xml VERSION | Applied manually (different target version) |
| `438ab57` | Bookmark error fix | Already in main via php82 branch |
| `a422783` | Align version numbers | Targets v1.5.0, not applicable to v2.0.0 |

## Step 2: Branch Cleanup

### Post-Consolidation Branch State

| Branch | State | Pushed To |
|--------|-------|-----------|
| `main` | **Consolidated** — 27 commits on origin | origin, bccc, github |
| `dev` | Reset to `main` | origin, bccc |
| `production-release` | Reset to `main` | origin, bccc |

### Deleted Branches (local + remote)
- `fix/bookmark-error` — origin
- `fix/php82-deprecations` — origin, bccc
- `fix/gh-issues-and-bookmarks` — origin (wasn't on bccc), bccc
- `fix/contrib-10263-reviewer-feedback-r2` — origin, bccc

## Step 3: Verification

### PHP Syntax Check
```
✅ All PHP files pass `php -l` — zero syntax errors
```

### AMD Module Check
```
✅ All amd/src/*.js files have matching amd/build/*.min.js files
```

### Version Info
```
Component: report_adeptus_insights
Version:   2026031601
Release:   2.0.0
Requires:  Moodle 4.1+ (2022112800)
Supported: 4.1 through 5.0
Maturity:  MATURITY_STABLE
```

### Final Commit Log (top 27)
```
5a9843b fix(CI): fix GPL license headers — remove trailing periods
d905fdc fix: migrate before_http_headers to hook system + remove forced Boost theme
1fd587f fix(CI): remove self-referencing symlink causing recursive directory scan
e16741b fix: Reports tab not loading - bootstrap.Tab crash + missing tab click handler
318e164 fix: schedule_form API endpoint + assistant init debugging
656af55 build: regenerate minified JS for assistant, auth_utils, cohort_group_filter
f1e0eaf fix: include filelib.php for curl class in schedule_form.php
f329480 fix: rewrite loadReportsHistory to use Ajax.call directly
9dd84aa Wire cohort/group filters to report re-execution on AI Assistant
e9346cb fix: show cohort/group filter bar on AI Assistant Reports tab
871075e fix: unwrap assistantPromise array in Promise.all callback
7deacc5 fix(G2): move filter bar inside report view
27d9bb3 fix: Generated Reports page — add filter CSS + fix $.when/Promise.all crash
01ac71e fix: remove Mustache tag from comment block causing stray }}
e7202e5 fix(G2): rewrite filter SQL injection — inject WHERE clause
727e103 fix: properly interpolate error message {} placeholder
3062b70 feat(G2): wire cohort/group filters through to report execution
c38a69c fix: declare PHP 8.2 class properties (A360-009)
5fadd52 fix: T66 — resolve curl fatal error, add learner_dashboard.php
e099d4e T64: Add missing capability lang strings for v2.0.0
c085299 chore: bump version to v2.0.0 (2026030400)
8ba87a0 T46: Report Builder Plugin UI (Phase 2)
d16dbde refactor(T33): remove jQuery dependency — Moodle 5.x ready
b4d48ff fix(T32): missing plans table upgrade step + alert decimal precision
339e336 docs: add CHANGELOG.md and GH issue triage report
cab3b3c docs: add CHANGELOG.md for v2.0.0 release
a384b1a fix(GH-26,T29): register external services + fix bookmark reportid
```

## Remotes Synced
- **origin** (gitlab.com:adeptus360/insights) — ✅
- **bccc** (bc-command-centre/adeptus-insights) — ✅
- **github** (Adeptus-360/moodle-report_adeptus_insights) — ✅

---

**Status: READY FOR E2E TESTING**
