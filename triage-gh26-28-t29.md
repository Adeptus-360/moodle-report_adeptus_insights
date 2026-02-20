# GitHub Issues #26, #27, #28 + T29 Triage Report

**Date:** 2026-02-20  
**Branch:** `fix/gh-issues-and-bookmarks`  
**Version:** 1.14.1 (2026022000)

---

## #26 — Missing External Services

**Status:** ⚠️ PARTIALLY FIXED (was incomplete, now fixed)

**Finding:** 7 external classes in `classes/external/` had no registration in `db/services.php`:
- `manage_category`
- `manage_generated_reports`
- `manage_recent_reports`
- `track_export`
- `track_report_created`
- `track_report_deleted`
- `update_report_category`

These classes existed and were called by the frontend JS, but Moodle's external API couldn't find them because they weren't registered as external functions.

**Fix:** Added all 7 function definitions + added them to the internal service list. PHP syntax verified, cache purged, upgrade successful.

---

## #27 — Missing DB Table in install.xml

**Status:** ✅ ALREADY FIXED

**Finding:** install.xml now contains **21 tables** covering all tables referenced in code. The `db/upgrade.php` file creates 2 legacy tables (`adeptus_generated_reports`, `adeptus_stripe_config`) that use old naming conventions — these are NOT referenced by any code outside upgrade.php itself. They exist only to handle upgrades from very early versions (v1.5 era) and are harmless.

All tables that code actually references are present in both install.xml (fresh installs) and upgrade.php (upgrades).

**No fix needed.**

---

## #28 — Incorrect Language String Placeholder

**Status:** ✅ ALREADY FIXED

**Finding:** Scanned all 1345+ language strings in `lang/en/report_adeptus_insights.php`. All placeholders use correct Moodle format:
- Simple: `{$a}` (e.g., `'Access ends: {$a}'`)
- Object: `{$a->property}` (e.g., `'{$a->used} / {$a->limit} Reports Generated'`)

Cross-referenced with PHP code usage — all `get_string()` calls pass appropriate `$a` parameters. No broken or malformed placeholders found.

**No fix needed.**

---

## T29 — Bookmark Error

**Status:** 🐛 BUG FOUND AND FIXED

**Root Cause:** The `report_adeptus_insights_bookmarks` table defined `reportid` as `TYPE="int" LENGTH="10"` in install.xml, but report IDs are text slugs (e.g., `"course_completion_overview"`). The external service correctly uses `PARAM_TEXT` for the reportid parameter. When a user tried to bookmark, the DB would reject the string value for an integer column.

**Fix:**
1. Changed `install.xml`: `reportid` from `int(10)` to `char(255)`
2. Added `idx_reportid` index for query performance
3. Added upgrade step `2026022000` with `change_field_type` to fix existing installations
4. Version bumped to 1.14.1

**Tested:** Upgrade ran successfully on Docker instance. PHP syntax clean.

---

## Summary

| Issue | Status | Action Taken |
|-------|--------|-------------|
| #26 Missing external services | Fixed | Added 7 service registrations |
| #27 Missing DB table | Already fixed | No change needed |
| #28 Language string placeholders | Already fixed | No change needed |
| T29 Bookmark error | Fixed | Changed reportid column type int→char |

**Commit:** `a384b1a` on branch `fix/gh-issues-and-bookmarks`  
**Pushed to:** GitLab (bccc remote)
